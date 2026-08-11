<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('updateApproval_requesterApprovesOwnRequest_forbidden', function () {
    // Arrange
    $requester = Employee::factory()->create();
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted', 'requested_by' => $requester->id]);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'step_order' => 1,
        'approver_employee_id' => $requester->id,
        'status' => 'active',
    ]);
    Sanctum::actingAs($requester, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'approve']);

    // Assert
    $response->assertForbidden()->assertJsonPath('message', 'Forbidden.');
});

test('updateApproval_notTheResolvedApprover_forbidden', function () {
    // Arrange
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted']);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'step_order' => 1,
        'approver_employee_id' => Employee::factory(),
        'status' => 'active',
    ]);
    $outsider = Employee::factory()->create();
    Sanctum::actingAs($outsider, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'approve']);

    // Assert
    $response->assertForbidden();
});

test('updateApproval_cancelBySomeoneOtherThanRequester_forbidden', function () {
    // Arrange
    $requester = Employee::factory()->create();
    $someoneElse = Employee::factory()->create();
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted', 'requested_by' => $requester->id]);
    ApprovalStep::factory()->create(['approval_request_id' => $approval->id, 'step_order' => 1, 'status' => 'active']);
    Sanctum::actingAs($someoneElse, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'cancel']);

    // Assert
    $response->assertForbidden();
});

test('updateApproval_departmentManagerPool_matchingDepartment_approved', function () {
    // Arrange
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $subject = Employee::factory()->create(['department_id' => $department->id]);
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted', 'subject_employee_id' => $subject->id]);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'step_order' => 1,
        'approver_kind' => 'department_manager',
        'approver_employee_id' => null,
        'status' => 'active',
    ]);
    Sanctum::actingAs($manager, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'reject']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'rejected');
});

test('updateApproval_departmentManagerPool_differentDepartment_forbidden', function () {
    // Arrange
    $subjectDepartment = Department::factory()->create();
    $managerDepartment = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $managerDepartment->id]);
    $subject = Employee::factory()->create(['department_id' => $subjectDepartment->id]);
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted', 'subject_employee_id' => $subject->id]);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'step_order' => 1,
        'approver_kind' => 'department_manager',
        'approver_employee_id' => null,
        'status' => 'active',
    ]);
    Sanctum::actingAs($manager, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'reject']);

    // Assert
    $response->assertForbidden();
});

test('updateApproval_employeePositionForDepartmentManagerStep_forbidden', function () {
    // Arrange - an `employee`-position colleague in the same department is not a manager
    $department = Department::factory()->create();
    $colleague = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $subject = Employee::factory()->create(['department_id' => $department->id]);
    $approval = ApprovalRequest::factory()->create(['status' => 'submitted', 'subject_employee_id' => $subject->id]);
    ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'step_order' => 1,
        'approver_kind' => 'department_manager',
        'approver_employee_id' => null,
        'status' => 'active',
    ]);
    Sanctum::actingAs($colleague, ['approvals:update']);

    // Act
    $response = $this->patchJson("/api/approvals/{$approval->id}", ['type' => 'reject']);

    // Assert
    $response->assertForbidden();
});

test('getApproval_requester_visible', function () {
    // Arrange
    $requester = Employee::factory()->create();
    $approval = ApprovalRequest::factory()->create(['requested_by' => $requester->id]);
    Sanctum::actingAs($requester, ['approvals:read']);

    // Act
    $response = $this->getJson("/api/approvals/{$approval->id}");

    // Assert
    $response->assertSuccessful();
});

test('getApproval_unrelatedEmployee_forbidden', function () {
    // Arrange
    $approval = ApprovalRequest::factory()->create();
    $outsider = Employee::factory()->create();
    Sanctum::actingAs($outsider, ['approvals:read']);

    // Act
    $response = $this->getJson("/api/approvals/{$approval->id}");

    // Assert
    $response->assertForbidden();
});
