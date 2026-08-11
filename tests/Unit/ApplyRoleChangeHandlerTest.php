<?php

use App\Enums\ProjectAssignmentStatus;
use App\Enums\RoleChangeMode;
use App\Models\ApprovalRequest;
use App\Models\AssignmentRolePeriod;
use App\Models\ProjectAssignment;
use App\Models\ProjectRole;
use App\Models\RoleChangeRequest;
use App\Services\Approval\Handlers\ApplyRoleChangeHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @return array{assignment: ProjectAssignment, roleA: ProjectRole, periodA: AssignmentRolePeriod}
 */
function makeAssignmentWithActiveRole(): array
{
    $assignment = ProjectAssignment::factory()->create(['status' => ProjectAssignmentStatus::Active]);
    $roleA = ProjectRole::factory()->create();
    $periodA = AssignmentRolePeriod::factory()->create([
        'project_assignment_id' => $assignment->id,
        'project_role_id' => $roleA->id,
    ]);

    return ['assignment' => $assignment, 'roleA' => $roleA, 'periodA' => $periodA];
}

function makeApprovalFor(RoleChangeRequest $roleChangeRequest): ApprovalRequest
{
    return ApprovalRequest::factory()->create([
        'requestable_type' => RoleChangeRequest::class,
        'requestable_id' => $roleChangeRequest->id,
    ]);
}

test('apply_addMode_createsNewRolePeriodKeepingOldActive', function () {
    // Arrange
    ['assignment' => $assignment, 'roleA' => $roleA] = makeAssignmentWithActiveRole();
    $roleB = ProjectRole::factory()->create();
    $roleChangeRequest = RoleChangeRequest::factory()->create([
        'project_assignment_id' => $assignment->id,
        'change_mode' => RoleChangeMode::Add,
        'from_project_role_id' => null,
        'to_project_role_id' => $roleB->id,
    ]);
    $approval = makeApprovalFor($roleChangeRequest);
    $handler = app(ApplyRoleChangeHandler::class);

    // Act
    $handler->apply($approval);

    // Assert
    $this->assertDatabaseHas('assignment_role_periods', [
        'project_assignment_id' => $assignment->id,
        'project_role_id' => $roleA->id,
        'end_date' => null,
    ]);
    $this->assertDatabaseHas('assignment_role_periods', [
        'project_assignment_id' => $assignment->id,
        'project_role_id' => $roleB->id,
        'end_date' => null,
        'source_approval_request_id' => $approval->id,
    ]);
});

test('apply_replaceMode_closesOldAndCreatesNew', function () {
    // Arrange
    ['assignment' => $assignment, 'roleA' => $roleA, 'periodA' => $periodA] = makeAssignmentWithActiveRole();
    $roleB = ProjectRole::factory()->create();
    $roleChangeRequest = RoleChangeRequest::factory()->create([
        'project_assignment_id' => $assignment->id,
        'change_mode' => RoleChangeMode::Replace,
        'from_project_role_id' => $roleA->id,
        'to_project_role_id' => $roleB->id,
    ]);
    $approval = makeApprovalFor($roleChangeRequest);
    $handler = app(ApplyRoleChangeHandler::class);

    // Act
    $handler->apply($approval);

    // Assert
    expect($periodA->fresh()->end_date->toDateString())->toBe(today()->toDateString());
    $this->assertDatabaseHas('assignment_role_periods', [
        'project_assignment_id' => $assignment->id,
        'project_role_id' => $roleB->id,
        'end_date' => null,
        'source_approval_request_id' => $approval->id,
    ]);
});

test('apply_removeMode_closesOldWithoutCreatingNew', function () {
    // Arrange
    ['assignment' => $assignment, 'roleA' => $roleA, 'periodA' => $periodA] = makeAssignmentWithActiveRole();
    $roleChangeRequest = RoleChangeRequest::factory()->create([
        'project_assignment_id' => $assignment->id,
        'change_mode' => RoleChangeMode::Remove,
        'from_project_role_id' => $roleA->id,
        'to_project_role_id' => null,
    ]);
    $approval = makeApprovalFor($roleChangeRequest);
    $handler = app(ApplyRoleChangeHandler::class);

    // Act
    $handler->apply($approval);

    // Assert
    expect($periodA->fresh()->end_date->toDateString())->toBe(today()->toDateString());
    expect(AssignmentRolePeriod::where('project_assignment_id', $assignment->id)->count())->toBe(1);
});

test('apply_addMode_targetRoleWentActiveSinceSubmit_throwsAndDoesNotDuplicate', function () {
    // Arrange - simulates a race: the target role was added through some other path
    // (e.g. a direct admin action) between submit time and this approval finally applying.
    ['assignment' => $assignment] = makeAssignmentWithActiveRole();
    $roleB = ProjectRole::factory()->create();
    AssignmentRolePeriod::factory()->create(['project_assignment_id' => $assignment->id, 'project_role_id' => $roleB->id]);
    $roleChangeRequest = RoleChangeRequest::factory()->create([
        'project_assignment_id' => $assignment->id,
        'change_mode' => RoleChangeMode::Add,
        'from_project_role_id' => null,
        'to_project_role_id' => $roleB->id,
    ]);
    $approval = makeApprovalFor($roleChangeRequest);
    $handler = app(ApplyRoleChangeHandler::class);

    // Act & Assert
    expect(fn () => $handler->apply($approval))->toThrow(ValidationException::class);
    expect(AssignmentRolePeriod::where('project_assignment_id', $assignment->id)->where('project_role_id', $roleB->id)->count())->toBe(1);
});

test('apply_replaceMode_sourceRoleNoLongerActive_throws', function () {
    // Arrange - the source role was already ended by some other path before this approval applied.
    ['assignment' => $assignment, 'roleA' => $roleA, 'periodA' => $periodA] = makeAssignmentWithActiveRole();
    $periodA->update(['end_date' => today()->toDateString()]);
    $roleC = ProjectRole::factory()->create();
    $roleChangeRequest = RoleChangeRequest::factory()->create([
        'project_assignment_id' => $assignment->id,
        'change_mode' => RoleChangeMode::Replace,
        'from_project_role_id' => $roleA->id,
        'to_project_role_id' => $roleC->id,
    ]);
    $approval = makeApprovalFor($roleChangeRequest);
    $handler = app(ApplyRoleChangeHandler::class);

    // Act & Assert
    expect(fn () => $handler->apply($approval))->toThrow(ValidationException::class);
});

test('apply_assignmentNoLongerActive_throws', function () {
    // Arrange
    ['assignment' => $assignment, 'roleA' => $roleA] = makeAssignmentWithActiveRole();
    $assignment->update(['status' => ProjectAssignmentStatus::Ended, 'end_date' => today()->toDateString()]);
    $roleChangeRequest = RoleChangeRequest::factory()->create([
        'project_assignment_id' => $assignment->id,
        'change_mode' => RoleChangeMode::Remove,
        'from_project_role_id' => $roleA->id,
        'to_project_role_id' => null,
    ]);
    $approval = makeApprovalFor($roleChangeRequest);
    $handler = app(ApplyRoleChangeHandler::class);

    // Act & Assert
    expect(fn () => $handler->apply($approval))->toThrow(ValidationException::class);
});
