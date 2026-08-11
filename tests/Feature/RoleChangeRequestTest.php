<?php

use App\Enums\ProjectStatus;
use App\Events\ApprovalRequestDecided;
use App\Events\ApprovalStepActivated;
use App\Models\AssignmentRolePeriod;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectManager;
use App\Models\ProjectRole;
use App\Services\ProjectAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array{project: Project, pm: Employee, employee: Employee, assignment: ProjectAssignment, roleA: ProjectRole}
 */
function setUpRoleChangeFixture(): array
{
    $pm = Employee::factory()->create(['position' => 'manager']);
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id]);
    $employee = Employee::factory()->create(['position' => 'employee']);
    $assignment = ProjectAssignment::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'assigned_by' => $pm->id,
    ]);
    $roleA = ProjectRole::factory()->create();
    AssignmentRolePeriod::factory()->create([
        'project_assignment_id' => $assignment->id,
        'project_role_id' => $roleA->id,
    ]);

    return compact('project', 'pm', 'employee', 'assignment', 'roleA');
}

test('createRoleChangeRequest_selfServiceAdd_submittedWithPmApprovalStep', function () {
    // Arrange
    ['pm' => $pm, 'employee' => $employee, 'assignment' => $assignment] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    Sanctum::actingAs($employee, ['role-change-requests:create']);

    // Act
    $response = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleB->id,
        'reason' => 'Picking up backend work too.',
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.change_mode', 'add')
        ->assertJsonPath('data.approval_request.status', 'submitted')
        ->assertJsonPath('data.approval_request.steps.0.approver_kind', 'project_manager')
        ->assertJsonPath('data.approval_request.steps.0.approver_employee_id', $pm->id);

    $this->assertDatabaseHas('role_change_requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleB->id,
        'created_by' => $employee->id,
    ]);
});

test('createRoleChangeRequest_projectManagerOnBehalf_submitted', function () {
    // Arrange
    ['pm' => $pm, 'assignment' => $assignment] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    Sanctum::actingAs($pm, ['role-change-requests:create']);

    // Act
    $response = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleB->id,
        'reason' => 'Promoting to Tech Lead work.',
    ]);

    // Assert - the requester is also the sole active PM, so the workflow routes to admin
    // instead of a step only the requester themselves could act on.
    $response->assertCreated()->assertJsonPath('data.approval_request.steps.0.approver_kind', 'system_admin');
});

test('createRoleChangeRequest_unrelatedManagerNotPm_forbidden', function () {
    // Arrange
    ['assignment' => $assignment] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    $outsider = Employee::factory()->create(['position' => 'manager']);
    Sanctum::actingAs($outsider, ['role-change-requests:create']);

    // Act
    $response = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleB->id,
        'reason' => 'Not my project.',
    ]);

    // Assert
    $response->assertForbidden();
});

test('createRoleChangeRequest_addModeMissingToRole_validationError', function () {
    // Arrange
    ['employee' => $employee, 'assignment' => $assignment] = setUpRoleChangeFixture();
    Sanctum::actingAs($employee, ['role-change-requests:create']);

    // Act
    $response = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'reason' => 'Missing target role.',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['to_project_role_id'], 'errors');
});

test('createRoleChangeRequest_removeModeWithToRole_validationError', function () {
    // Arrange
    ['employee' => $employee, 'assignment' => $assignment, 'roleA' => $roleA] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    Sanctum::actingAs($employee, ['role-change-requests:create']);

    // Act
    $response = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'remove',
        'from_project_role_id' => $roleA->id,
        'to_project_role_id' => $roleB->id,
        'reason' => 'Should not include a target role.',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['to_project_role_id'], 'errors');
});

test('createRoleChangeRequest_replaceModeSameFromAndTo_validationError', function () {
    // Arrange
    ['employee' => $employee, 'assignment' => $assignment, 'roleA' => $roleA] = setUpRoleChangeFixture();
    Sanctum::actingAs($employee, ['role-change-requests:create']);

    // Act
    $response = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'replace',
        'from_project_role_id' => $roleA->id,
        'to_project_role_id' => $roleA->id,
        'reason' => 'Same role twice.',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['to_project_role_id'], 'errors');
});

test('createRoleChangeRequest_addModeTargetRoleAlreadyActive_validationError', function () {
    // Arrange
    ['employee' => $employee, 'assignment' => $assignment, 'roleA' => $roleA] = setUpRoleChangeFixture();
    Sanctum::actingAs($employee, ['role-change-requests:create']);

    // Act
    $response = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleA->id,
        'reason' => 'Already has this role.',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['to_project_role_id'], 'errors');
});

test('createRoleChangeRequest_removeModeSourceRoleNotActive_validationError', function () {
    // Arrange
    ['employee' => $employee, 'assignment' => $assignment] = setUpRoleChangeFixture();
    $notActiveRole = ProjectRole::factory()->create();
    Sanctum::actingAs($employee, ['role-change-requests:create']);

    // Act
    $response = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'remove',
        'from_project_role_id' => $notActiveRole->id,
        'reason' => 'Not actually active.',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['from_project_role_id'], 'errors');
});

test('createRoleChangeRequest_duplicatePendingRequest_validationError', function () {
    // Arrange
    ['employee' => $employee, 'assignment' => $assignment] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    $roleC = ProjectRole::factory()->create();
    Sanctum::actingAs($employee, ['role-change-requests:create']);
    $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleB->id,
        'reason' => 'First request.',
    ])->assertCreated();

    // Act
    $response = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleC->id,
        'reason' => 'Second request while first still pending.',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['project_assignment_id'], 'errors');
});

test('roleChangeRequest_addModeApprovedByPm_appliedAndRoleAdded', function () {
    // Arrange
    ['pm' => $pm, 'employee' => $employee, 'assignment' => $assignment, 'roleA' => $roleA] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    Sanctum::actingAs($employee, ['role-change-requests:create']);
    $created = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleB->id,
        'reason' => 'Picking up backend work too.',
    ])->json('data');
    Sanctum::actingAs($pm, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$created['approval_request']['id']}", ['type' => 'approve']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'applied');
    $this->assertDatabaseHas('assignment_role_periods', [
        'project_assignment_id' => $assignment->id,
        'project_role_id' => $roleA->id,
        'end_date' => null,
    ]);
    $this->assertDatabaseHas('assignment_role_periods', [
        'project_assignment_id' => $assignment->id,
        'project_role_id' => $roleB->id,
        'end_date' => null,
        'source_approval_request_id' => $created['approval_request']['id'],
    ]);
});

test('roleChangeRequest_replaceModeApprovedByPm_appliedOldClosedNewCreated', function () {
    // Arrange
    ['pm' => $pm, 'employee' => $employee, 'assignment' => $assignment, 'roleA' => $roleA] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    Sanctum::actingAs($employee, ['role-change-requests:create']);
    $created = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'replace',
        'from_project_role_id' => $roleA->id,
        'to_project_role_id' => $roleB->id,
        'reason' => 'Moving to Tech Lead.',
    ])->json('data');
    Sanctum::actingAs($pm, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$created['approval_request']['id']}", ['type' => 'approve']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'applied');
    expect(AssignmentRolePeriod::where('project_assignment_id', $assignment->id)->where('project_role_id', $roleA->id)->first()->end_date->toDateString())->toBe(today()->toDateString());
    $this->assertDatabaseHas('assignment_role_periods', [
        'project_assignment_id' => $assignment->id,
        'project_role_id' => $roleB->id,
        'end_date' => null,
    ]);
});

test('roleChangeRequest_removeModeApprovedByPm_appliedOldClosed', function () {
    // Arrange
    ['pm' => $pm, 'employee' => $employee, 'assignment' => $assignment, 'roleA' => $roleA] = setUpRoleChangeFixture();
    Sanctum::actingAs($employee, ['role-change-requests:create']);
    $created = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'remove',
        'from_project_role_id' => $roleA->id,
        'reason' => 'Dropping this role.',
    ])->json('data');
    Sanctum::actingAs($pm, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$created['approval_request']['id']}", ['type' => 'approve']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'applied');
    expect(AssignmentRolePeriod::where('project_assignment_id', $assignment->id)->where('project_role_id', $roleA->id)->first()->end_date->toDateString())->toBe(today()->toDateString());
});

test('roleChangeRequest_rejectedByPm_rejectedWithoutRoleChange', function () {
    // Arrange
    ['pm' => $pm, 'employee' => $employee, 'assignment' => $assignment, 'roleA' => $roleA] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    Sanctum::actingAs($employee, ['role-change-requests:create']);
    $created = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleB->id,
        'reason' => 'Picking up backend work too.',
    ])->json('data');
    Sanctum::actingAs($pm, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$created['approval_request']['id']}", ['type' => 'reject', 'comment' => 'Not needed right now.']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'rejected');
    $this->assertDatabaseMissing('assignment_role_periods', [
        'project_assignment_id' => $assignment->id,
        'project_role_id' => $roleB->id,
    ]);
    $this->assertDatabaseHas('assignment_role_periods', [
        'project_assignment_id' => $assignment->id,
        'project_role_id' => $roleA->id,
        'end_date' => null,
    ]);
});

test('roleChangeRequest_targetRoleWentActiveBeforeApproval_failedNotApplied', function () {
    // Arrange - simulates a race: the target role gets added through a direct admin action
    // after this request was submitted but before the PM approves it.
    ['pm' => $pm, 'employee' => $employee, 'assignment' => $assignment] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    Sanctum::actingAs($employee, ['role-change-requests:create']);
    $created = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleB->id,
        'reason' => 'Picking up backend work too.',
    ])->json('data');
    app(ProjectAssignmentService::class)->addRole($assignment, ['project_role_id' => $roleB->id]);
    Sanctum::actingAs($pm, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$created['approval_request']['id']}", ['type' => 'approve']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'failed');
    expect(AssignmentRolePeriod::where('project_assignment_id', $assignment->id)->where('project_role_id', $roleB->id)->count())->toBe(1);
});

test('createRoleChangeRequest_submitted_dispatchesApprovalStepActivatedForPm', function () {
    // Arrange
    Event::fake([ApprovalStepActivated::class]);
    ['pm' => $pm, 'employee' => $employee, 'assignment' => $assignment] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    Sanctum::actingAs($employee, ['role-change-requests:create']);

    // Act
    $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleB->id,
        'reason' => 'Picking up backend work too.',
    ])->assertCreated();

    // Assert
    Event::assertDispatched(ApprovalStepActivated::class, fn ($event) => $event->step->approver_employee_id === $pm->id && $event->step->step_order === 1
    );
});

test('roleChangeRequest_approvedOnLastStep_dispatchesApprovalRequestDecidedApplied', function () {
    // Arrange
    ['pm' => $pm, 'employee' => $employee, 'assignment' => $assignment] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    Sanctum::actingAs($employee, ['role-change-requests:create']);
    $created = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleB->id,
        'reason' => 'Picking up backend work too.',
    ])->json('data');
    Event::fake([ApprovalRequestDecided::class]);
    Sanctum::actingAs($pm, ['approvals:update']);

    // Act
    $this->patchJson("/api/approvals/{$created['approval_request']['id']}", ['type' => 'approve'])->assertSuccessful();

    // Assert
    Event::assertDispatched(ApprovalRequestDecided::class, fn ($event) => $event->approval->id === $created['approval_request']['id']
        && $event->approval->status->value === 'applied'
    );
});

test('roleChangeRequest_rejected_dispatchesApprovalRequestDecidedRejected', function () {
    // Arrange
    ['pm' => $pm, 'employee' => $employee, 'assignment' => $assignment] = setUpRoleChangeFixture();
    $roleB = ProjectRole::factory()->create();
    Sanctum::actingAs($employee, ['role-change-requests:create']);
    $created = $this->postJson('/api/role-change-requests', [
        'project_assignment_id' => $assignment->id,
        'change_mode' => 'add',
        'to_project_role_id' => $roleB->id,
        'reason' => 'Picking up backend work too.',
    ])->json('data');
    Event::fake([ApprovalRequestDecided::class]);
    Sanctum::actingAs($pm, ['approvals:update']);

    // Act
    $this->patchJson("/api/approvals/{$created['approval_request']['id']}", ['type' => 'reject'])->assertSuccessful();

    // Assert
    Event::assertDispatched(ApprovalRequestDecided::class, fn ($event) => $event->approval->status->value === 'rejected'
    );
});
