<?php

use App\Enums\ProjectAssignmentStatus;
use App\Enums\ProjectStatus;
use App\Events\ApprovalRequestDecided;
use App\Events\ApprovalStepActivated;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array{employee: Employee, hrManager: Employee, pm: Employee, project: Project, assignment: ProjectAssignment}
 */
function setUpLeaveRequestFixture(): array
{
    $hrManager = Employee::factory()->create(['position' => 'manager']);
    $employee = Employee::factory()->create(['position' => 'employee', 'manager_employee_id' => $hrManager->id]);
    $pm = Employee::factory()->create(['position' => 'manager']);
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id]);
    $assignment = ProjectAssignment::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'status' => ProjectAssignmentStatus::Active,
        'assigned_by' => $pm->id,
    ]);

    return compact('employee', 'hrManager', 'pm', 'project', 'assignment');
}

test('createLeaveRequest_withActiveAssignment_submittedWithPmThenHrSteps', function () {
    // Arrange
    ['employee' => $employee, 'hrManager' => $hrManager, 'pm' => $pm, 'project' => $project] = setUpLeaveRequestFixture();
    Sanctum::actingAs($employee, ['leave-requests:create']);

    // Act
    $response = $this->postJson('/api/leave-requests', [
        'project_id' => $project->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'reason' => 'Family trip',
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.employee_id', $employee->id)
        ->assertJsonPath('data.project_id', $project->id)
        ->assertJsonPath('data.approval_request.status', 'submitted')
        ->assertJsonCount(2, 'data.approval_request.steps')
        ->assertJsonPath('data.approval_request.steps.0.approver_kind', 'project_manager')
        ->assertJsonPath('data.approval_request.steps.0.approver_employee_id', $pm->id)
        ->assertJsonPath('data.approval_request.steps.0.status', 'active')
        ->assertJsonPath('data.approval_request.steps.1.approver_kind', 'direct_manager')
        ->assertJsonPath('data.approval_request.steps.1.approver_employee_id', $hrManager->id)
        ->assertJsonPath('data.approval_request.steps.1.status', 'pending');

    $this->assertDatabaseHas('leave_requests', [
        'employee_id' => $employee->id,
        'project_id' => $project->id,
    ]);
});

test('createLeaveRequest_withoutActiveAssignment_submittedWithHrStepOnly', function () {
    // Arrange
    $hrManager = Employee::factory()->create(['position' => 'manager']);
    $employee = Employee::factory()->create(['position' => 'employee', 'manager_employee_id' => $hrManager->id]);
    Sanctum::actingAs($employee, ['leave-requests:create']);

    // Act
    $response = $this->postJson('/api/leave-requests', [
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'reason' => 'No project assignment yet',
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.project_id', null)
        ->assertJsonCount(1, 'data.approval_request.steps')
        ->assertJsonPath('data.approval_request.steps.0.approver_kind', 'direct_manager')
        ->assertJsonPath('data.approval_request.steps.0.approver_employee_id', $hrManager->id);
});

test('createLeaveRequest_employeeIsSoleActivePmOfPickedProject_pmStepSkippedHrOnly', function () {
    // Arrange - the requester is themselves the picked project's only active PM; since HR is
    // still a mandatory second gate, the PM step is dropped entirely rather than routed to
    // SystemAdmin the way RoleChangeApprovalWorkflow would.
    $hrManager = Employee::factory()->create(['position' => 'manager']);
    $employee = Employee::factory()->create(['position' => 'manager', 'manager_employee_id' => $hrManager->id]);
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id]);
    ProjectAssignment::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'status' => ProjectAssignmentStatus::Active,
        'assigned_by' => $employee->id,
    ]);
    Sanctum::actingAs($employee, ['leave-requests:create']);

    // Act
    $response = $this->postJson('/api/leave-requests', [
        'project_id' => $project->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'reason' => 'Solo PM taking a break',
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonCount(1, 'data.approval_request.steps')
        ->assertJsonPath('data.approval_request.steps.0.approver_kind', 'direct_manager')
        ->assertJsonPath('data.approval_request.steps.0.approver_employee_id', $hrManager->id);
});

test('createLeaveRequest_managerEmployeeIdNull_hrStepFallsBackToSystemAdmin', function () {
    // Arrange - topmost employee, no manager at all
    $employee = Employee::factory()->create(['position' => 'employee', 'manager_employee_id' => null]);
    Sanctum::actingAs($employee, ['leave-requests:create']);

    // Act
    $response = $this->postJson('/api/leave-requests', [
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'reason' => 'No manager on file',
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonCount(1, 'data.approval_request.steps')
        ->assertJsonPath('data.approval_request.steps.0.approver_kind', 'system_admin')
        ->assertJsonPath('data.approval_request.steps.0.approver_employee_id', null);
});

test('createLeaveRequest_endDateBeforeStartDate_validationError', function () {
    // Arrange
    Sanctum::actingAs(Employee::factory()->create(), ['leave-requests:create']);

    // Act
    $response = $this->postJson('/api/leave-requests', [
        'start_date' => '2026-09-05',
        'end_date' => '2026-09-01',
        'reason' => 'Invalid range',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['end_date'], 'errors');
});

test('createLeaveRequest_missingRequiredFields_validationError', function () {
    // Arrange
    Sanctum::actingAs(Employee::factory()->create(), ['leave-requests:create']);

    // Act
    $response = $this->postJson('/api/leave-requests', []);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['start_date', 'end_date', 'reason'], 'errors');
});

test('createLeaveRequest_projectIdNotOwnActiveAssignment_validationError', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    $unrelatedProject = Project::factory()->create();
    Sanctum::actingAs($employee, ['leave-requests:create']);

    // Act
    $response = $this->postJson('/api/leave-requests', [
        'project_id' => $unrelatedProject->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'reason' => 'Not actually on this project',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['project_id'], 'errors');
});

test('createLeaveRequest_hasActiveAssignmentButProjectIdOmitted_validationError', function () {
    // Arrange
    ['employee' => $employee] = setUpLeaveRequestFixture();
    Sanctum::actingAs($employee, ['leave-requests:create']);

    // Act
    $response = $this->postJson('/api/leave-requests', [
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'reason' => 'Forgot to pick a project',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['project_id'], 'errors');
});

test('createLeaveRequest_overlappingPendingRequest_validationError', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    Sanctum::actingAs($employee, ['leave-requests:create']);
    $this->postJson('/api/leave-requests', [
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-05',
        'reason' => 'First request',
    ])->assertCreated();

    // Act
    $response = $this->postJson('/api/leave-requests', [
        'start_date' => '2026-09-03',
        'end_date' => '2026-09-08',
        'reason' => 'Overlaps the first',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['start_date'], 'errors');
});

test('createLeaveRequest_overlappingAlreadyAppliedRequest_validationError', function () {
    // Arrange - requesting leave overlapping dates already fully approved (Applied) is
    // just as invalid as overlapping another still-pending request.
    ['employee' => $employee, 'hrManager' => $hrManager, 'pm' => $pm, 'project' => $project] = setUpLeaveRequestFixture();
    Sanctum::actingAs($employee, ['leave-requests:create']);
    $created = $this->postJson('/api/leave-requests', [
        'project_id' => $project->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-05',
        'reason' => 'First request',
    ])->json('data');
    $approvalId = $created['approval_request']['id'];
    Sanctum::actingAs($pm, ['approvals:update']);
    $this->patchJson("/api/approvals/{$approvalId}", ['type' => 'approve'])->assertSuccessful();
    Sanctum::actingAs($hrManager, ['approvals:update']);
    $this->patchJson("/api/approvals/{$approvalId}", ['type' => 'approve'])->assertJsonPath('data.status', 'applied');
    Sanctum::actingAs($employee, ['leave-requests:create']);

    // Act
    $response = $this->postJson('/api/leave-requests', [
        'project_id' => $project->id,
        'start_date' => '2026-09-03',
        'end_date' => '2026-09-08',
        'reason' => 'Overlaps the already-applied leave',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['start_date'], 'errors');
});

test('getLeaveRequest_unknownId_notFound', function () {
    // Arrange
    Sanctum::actingAs(Employee::factory()->create(['position' => 'admin']), ['*']);

    // Act
    $response = $this->getJson('/api/leave-requests/999999');

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Resource not found.');
});

test('leaveRequest_approvedThroughBothSteps_applied', function () {
    // Arrange
    ['employee' => $employee, 'hrManager' => $hrManager, 'pm' => $pm, 'project' => $project] = setUpLeaveRequestFixture();
    Sanctum::actingAs($employee, ['leave-requests:create']);
    $created = $this->postJson('/api/leave-requests', [
        'project_id' => $project->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'reason' => 'Family trip',
    ])->json('data');
    $approvalId = $created['approval_request']['id'];

    // Act - PM approves first
    Sanctum::actingAs($pm, ['approvals:update']);
    $afterPm = $this->patchJson("/api/approvals/{$approvalId}", ['type' => 'approve']);

    // Assert - still in review, HR step now active
    $afterPm->assertSuccessful()
        ->assertJsonPath('data.status', 'in_review')
        ->assertJsonPath('data.steps.1.status', 'active');

    // Act - HR approves second (last step)
    Sanctum::actingAs($hrManager, ['approvals:update']);
    $afterHr = $this->patchJson("/api/approvals/{$approvalId}", ['type' => 'approve']);

    // Assert
    $afterHr->assertSuccessful()->assertJsonPath('data.status', 'applied');
});

test('leaveRequest_rejectedAtPmStep_hrStepNeverActivated', function () {
    // Arrange
    ['employee' => $employee, 'pm' => $pm, 'project' => $project] = setUpLeaveRequestFixture();
    Sanctum::actingAs($employee, ['leave-requests:create']);
    $created = $this->postJson('/api/leave-requests', [
        'project_id' => $project->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'reason' => 'Family trip',
    ])->json('data');
    $approvalId = $created['approval_request']['id'];
    Sanctum::actingAs($pm, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approvalId}", ['type' => 'reject', 'comment' => 'Bad timing, project deadline this week.']);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.steps.1.status', 'pending');
});

test('createLeaveRequest_submitted_dispatchesApprovalStepActivatedForPm', function () {
    // Arrange
    Event::fake([ApprovalStepActivated::class]);
    ['employee' => $employee, 'pm' => $pm, 'project' => $project] = setUpLeaveRequestFixture();
    Sanctum::actingAs($employee, ['leave-requests:create']);

    // Act
    $this->postJson('/api/leave-requests', [
        'project_id' => $project->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'reason' => 'Family trip',
    ])->assertCreated();

    // Assert
    Event::assertDispatched(ApprovalStepActivated::class, fn ($event) => $event->step->approver_employee_id === $pm->id && $event->step->step_order === 1
    );
});

test('leaveRequest_appliedOnLastStep_dispatchesApprovalRequestDecided', function () {
    // Arrange
    ['employee' => $employee, 'hrManager' => $hrManager, 'pm' => $pm, 'project' => $project] = setUpLeaveRequestFixture();
    Sanctum::actingAs($employee, ['leave-requests:create']);
    $created = $this->postJson('/api/leave-requests', [
        'project_id' => $project->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'reason' => 'Family trip',
    ])->json('data');
    $approvalId = $created['approval_request']['id'];
    Sanctum::actingAs($pm, ['approvals:update']);
    $this->patchJson("/api/approvals/{$approvalId}", ['type' => 'approve'])->assertSuccessful();
    Event::fake([ApprovalRequestDecided::class]);
    Sanctum::actingAs($hrManager, ['approvals:update']);

    // Act
    $this->patchJson("/api/approvals/{$approvalId}", ['type' => 'approve'])->assertSuccessful();

    // Assert
    Event::assertDispatched(ApprovalRequestDecided::class, fn ($event) => $event->approval->id === $approvalId
        && $event->approval->status->value === 'applied'
    );
});
