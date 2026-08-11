<?php

use App\Enums\WorkflowType;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Services\Approval\ApprovedRequestHandlerRegistry;
use App\Services\Approval\Contracts\ApprovedRequestHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * No concrete workflow ships in Phase D, so these tests seed approval_requests/steps
 * directly via factories (bypassing ApprovalRequestService::submit()) and bind a
 * test-only fake ApprovedRequestHandler for WorkflowType::ProjectRoleChange - the
 * factory's default workflow_type - to exercise the real approve -> Approved -> apply
 * path without any real business handler existing yet.
 */
function bindFakeApprovedRequestHandler(bool $throws = false): void
{
    $handler = new class($throws) implements ApprovedRequestHandler
    {
        public function __construct(private readonly bool $throws) {}

        public function type(): WorkflowType
        {
            return WorkflowType::ProjectRoleChange;
        }

        public function apply(ApprovalRequest $approval): void
        {
            if ($this->throws) {
                throw new RuntimeException('Stale data.');
            }
        }
    };

    app()->instance(ApprovedRequestHandlerRegistry::class, new ApprovedRequestHandlerRegistry([$handler]));
}

test('listApprovals_admin_allReturned', function () {
    // Arrange
    ApprovalRequest::factory()->count(2)->create();
    Sanctum::actingAs(Employee::factory()->create(['position' => 'admin']), ['*']);

    // Act
    $response = $this->getJson('/api/approvals');

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(2);
});

test('listApprovals_employeeMineFilter_scopedToOwn', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    $mine = ApprovalRequest::factory()->create(['requested_by' => $employee->id]);
    ApprovalRequest::factory()->create();
    Sanctum::actingAs($employee, ['approvals:read']);

    // Act
    $response = $this->getJson('/api/approvals?mine=1');

    // Assert
    $response->assertSuccessful();
    $ids = collect($response->json('data.data'))->pluck('id')->all();
    expect($ids)->toBe([$mine->id]);
});

test('listApprovals_employeeDefault_unrelatedRequestNotVisible', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    ApprovalRequest::factory()->create();
    Sanctum::actingAs($employee, ['approvals:read']);

    // Act
    $response = $this->getJson('/api/approvals');

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(0);
});

test('getApproval_unknownId_notFound', function () {
    // Arrange
    Sanctum::actingAs(Employee::factory()->create(['position' => 'admin']), ['*']);

    // Act
    $response = $this->getJson('/api/approvals/999999');

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Resource not found.');
});

test('updateApproval_lastStepApprove_appliedWhenHandlerSucceeds', function () {
    // Arrange
    $approver = Employee::factory()->create();
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted']);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'step_order' => 1,
        'approver_employee_id' => $approver->id,
        'status' => 'active',
    ]);
    bindFakeApprovedRequestHandler();
    Sanctum::actingAs($approver, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'approve']);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'applied')
        ->assertJsonPath('data.steps.0.status', 'approved');

    $this->assertDatabaseHas('approval_requests', ['id' => $approval->id, 'status' => 'applied']);
});

test('updateApproval_lastStepApprove_failedWhenHandlerThrows', function () {
    // Arrange
    $approver = Employee::factory()->create();
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted']);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'step_order' => 1,
        'approver_employee_id' => $approver->id,
        'status' => 'active',
    ]);
    bindFakeApprovedRequestHandler(throws: true);
    Sanctum::actingAs($approver, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'approve']);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'Stale data.');
});

test('updateApproval_notLastStepApprove_advancesToNextStepInReview', function () {
    // Arrange
    $firstApprover = Employee::factory()->create();
    $secondApprover = Employee::factory()->create();
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted']);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'step_order' => 1,
        'approver_employee_id' => $firstApprover->id,
        'status' => 'active',
    ]);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'step_order' => 2,
        'approver_employee_id' => $secondApprover->id,
        'status' => 'pending',
    ]);
    Sanctum::actingAs($firstApprover, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'approve']);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'in_review')
        ->assertJsonPath('data.current_step_order', 2);

    $this->assertDatabaseHas('approval_steps', ['approval_request_id' => $approval->id, 'step_order' => 2, 'status' => 'active']);
});

test('updateApproval_reject_terminatesWholeRequestNotJustStep', function () {
    // Arrange
    $approver = Employee::factory()->create();
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted']);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'step_order' => 1,
        'approver_employee_id' => $approver->id,
        'status' => 'active',
    ]);
    ApprovalStep::factory()->create(['approval_request_id' => $approval->id, 'step_order' => 2, 'status' => 'pending']);
    Sanctum::actingAs($approver, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'reject', 'comment' => 'Not eligible']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'rejected');
    $this->assertDatabaseHas('approval_steps', ['approval_request_id' => $approval->id, 'step_order' => 2, 'status' => 'pending']);
});

test('updateApproval_cancelByRequester_cancelled', function () {
    // Arrange
    $requester = Employee::factory()->create();
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted', 'requested_by' => $requester->id]);
    ApprovalStep::factory()->create(['approval_request_id' => $approval->id, 'step_order' => 1, 'status' => 'active']);
    Sanctum::actingAs($requester, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'cancel']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'cancelled');
    $this->assertDatabaseHas('approval_steps', ['approval_request_id' => $approval->id, 'step_order' => 1, 'status' => 'skipped']);
});

test('updateApproval_adminApprovesAlreadyResolvedRequest_validationError', function () {
    // Arrange - admin bypasses the approver-eligibility policy check, so this exercises
    // the state machine's own guard against acting on a request that's no longer awaiting
    // approval (defense-in-depth against double-approval/races).
    $approval = ApprovalRequest::factory()->create(['status' => 'approved']);
    ApprovalStep::factory()->create(['approval_request_id' => $approval->id, 'step_order' => 1, 'status' => 'approved']);
    Sanctum::actingAs(Employee::factory()->create(['position' => 'admin']), ['*']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'approve']);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['approval'], 'errors');
});

test('updateApproval_sameApproverApprovesTwice_secondCallForbidden', function () {
    // Arrange - simulates a race: once the step is resolved and the workflow advances,
    // the same approver is no longer the resolved approver of anything on this request.
    $approver = Employee::factory()->create();
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted']);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'step_order' => 1,
        'approver_employee_id' => $approver->id,
        'status' => 'active',
    ]);
    bindFakeApprovedRequestHandler();
    Sanctum::actingAs($approver, ['approvals:update']);

    // Act
    $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'approve'])->assertSuccessful();
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'approve']);

    // Assert
    $response->assertForbidden();
});

test('updateApproval_invalidType_validationError', function () {
    // Arrange
    $approval = ApprovalRequest::factory()->create();
    Sanctum::actingAs(Employee::factory()->create(['position' => 'admin']), ['*']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'delete']);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['type'], 'errors');
});
