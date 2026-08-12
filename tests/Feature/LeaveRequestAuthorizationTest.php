<?php

use App\Enums\ProjectAssignmentStatus;
use App\Enums\ProjectStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array{employee: Employee, hrManager: Employee, pm: Employee, project: Project, leaveRequest: LeaveRequest}
 */
function setUpLeaveRequestForViewing(): array
{
    $hrManager = Employee::factory()->create(['position' => 'manager']);
    $employee = Employee::factory()->create(['position' => 'employee', 'manager_employee_id' => $hrManager->id]);
    $pm = Employee::factory()->create(['position' => 'manager']);
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id]);
    ProjectAssignment::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'status' => ProjectAssignmentStatus::Active,
        'assigned_by' => $pm->id,
    ]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'project_id' => $project->id]);

    return compact('employee', 'hrManager', 'pm', 'project', 'leaveRequest');
}

test('viewLeaveRequest_ownRequest_allowed', function () {
    // Arrange
    ['employee' => $employee, 'leaveRequest' => $leaveRequest] = setUpLeaveRequestForViewing();
    Sanctum::actingAs($employee, ['leave-requests:read']);

    // Act
    $response = $this->getJson("/api/leave-requests/{$leaveRequest->id}");

    // Assert
    $response->assertSuccessful();
});

test('viewLeaveRequest_currentPmOfPickedProject_allowed', function () {
    // Arrange
    ['pm' => $pm, 'leaveRequest' => $leaveRequest] = setUpLeaveRequestForViewing();
    Sanctum::actingAs($pm, ['leave-requests:read']);

    // Act
    $response = $this->getJson("/api/leave-requests/{$leaveRequest->id}");

    // Assert
    $response->assertSuccessful();
});

test('viewLeaveRequest_currentDirectManager_allowed', function () {
    // Arrange
    ['hrManager' => $hrManager, 'leaveRequest' => $leaveRequest] = setUpLeaveRequestForViewing();
    Sanctum::actingAs($hrManager, ['leave-requests:read']);

    // Act
    $response = $this->getJson("/api/leave-requests/{$leaveRequest->id}");

    // Assert
    $response->assertSuccessful();
});

test('viewLeaveRequest_admin_allowed', function () {
    // Arrange
    ['leaveRequest' => $leaveRequest] = setUpLeaveRequestForViewing();
    Sanctum::actingAs(Employee::factory()->create(['position' => 'admin']), ['*']);

    // Act
    $response = $this->getJson("/api/leave-requests/{$leaveRequest->id}");

    // Assert
    $response->assertSuccessful();
});

test('viewLeaveRequest_unrelatedEmployee_forbidden', function () {
    // Arrange
    ['leaveRequest' => $leaveRequest] = setUpLeaveRequestForViewing();
    $outsider = Employee::factory()->create(['position' => 'employee']);
    Sanctum::actingAs($outsider, ['leave-requests:read']);

    // Act
    $response = $this->getJson("/api/leave-requests/{$leaveRequest->id}");

    // Assert
    $response->assertForbidden();
});

test('viewLeaveRequest_unrelatedManagerNeitherPmNorDirectManager_forbidden', function () {
    // Arrange
    ['leaveRequest' => $leaveRequest] = setUpLeaveRequestForViewing();
    $unrelatedManager = Employee::factory()->create(['position' => 'manager']);
    Sanctum::actingAs($unrelatedManager, ['leave-requests:read']);

    // Act
    $response = $this->getJson("/api/leave-requests/{$leaveRequest->id}");

    // Assert
    $response->assertForbidden();
});

test('viewLeaveRequest_noProjectPicked_onlyOwnerAndDirectManagerCanView', function () {
    // Arrange - no project_id, so the "current PM" branch never applies
    $hrManager = Employee::factory()->create(['position' => 'manager']);
    $employee = Employee::factory()->create(['position' => 'employee', 'manager_employee_id' => $hrManager->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'project_id' => null]);
    $unrelatedManager = Employee::factory()->create(['position' => 'manager']);
    Sanctum::actingAs($unrelatedManager, ['leave-requests:read']);

    // Act
    $response = $this->getJson("/api/leave-requests/{$leaveRequest->id}");

    // Assert
    $response->assertForbidden();
});

test('viewLeaveRequest_resolvedPmStepApproverNoLongerCurrentPm_stillAllowed', function () {
    // Arrange - a step's approver_employee_id is locked in at submit time
    // (ApprovalRequestService::submit()); if that person is later removed as the
    // project's PM, they must still be able to view the request they can still legally
    // act on via ApprovalRequestPolicy::canActOnStep() (which matches the stored id, not
    // current PM status).
    ['employee' => $employee, 'pm' => $pm, 'project' => $project] = setUpLeaveRequestForViewing();
    Sanctum::actingAs($employee, ['leave-requests:create']);
    $created = $this->postJson('/api/leave-requests', [
        'project_id' => $project->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'reason' => 'Family trip',
    ])->json('data');
    ProjectManager::where('project_id', $project->id)->where('employee_id', $pm->id)->update(['end_date' => today()]);
    Sanctum::actingAs($pm, ['leave-requests:read']);

    // Act
    $response = $this->getJson("/api/leave-requests/{$created['id']}");

    // Assert
    $response->assertSuccessful();
});

test('approveLeaveRequest_requesterCannotApproveOwnRequest_forbidden', function () {
    // Arrange
    ['employee' => $employee, 'project' => $project] = setUpLeaveRequestForViewing();
    Sanctum::actingAs($employee, ['leave-requests:create']);
    $created = $this->postJson('/api/leave-requests', [
        'project_id' => $project->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'reason' => 'Family trip',
    ])->json('data');
    Sanctum::actingAs($employee, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$created['approval_request']['id']}", ['type' => 'approve']);

    // Assert
    $response->assertForbidden();
});

test('approveLeaveRequest_unresolvedApproverForActiveStep_forbidden', function () {
    // Arrange - a manager who is neither the resolved PM nor the resolved HR approver
    ['employee' => $employee, 'project' => $project] = setUpLeaveRequestForViewing();
    Sanctum::actingAs($employee, ['leave-requests:create']);
    $created = $this->postJson('/api/leave-requests', [
        'project_id' => $project->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'reason' => 'Family trip',
    ])->json('data');
    $outsiderManager = Employee::factory()->create(['position' => 'manager']);
    Sanctum::actingAs($outsiderManager, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$created['approval_request']['id']}", ['type' => 'approve']);

    // Assert
    $response->assertForbidden();
});
