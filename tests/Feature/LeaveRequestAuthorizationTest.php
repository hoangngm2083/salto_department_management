<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('reviewLeaveRequest_managerOwnDepartment_approved', function () {
    // Arrange
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    Sanctum::actingAs($manager, ['leave-requests:update']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", [
        'status' => 'approved',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.reviewer_name', $manager->name);

    $this->assertDatabaseHas('leave_requests', [
        'id' => $leaveRequest->id,
        'status' => 'approved',
        'reviewed_by' => $manager->id,
    ]);
});

test('reviewLeaveRequest_managerOwnDepartment_rejected', function () {
    // Arrange
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    Sanctum::actingAs($manager, ['leave-requests:update']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", [
        'status' => 'rejected',
        'review_note' => 'Too many overlapping requests',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.review_note', 'Too many overlapping requests');
});

test('reviewLeaveRequest_managerOtherDepartment_forbidden', function () {
    // Arrange
    $managerDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $managerDepartment->id]);
    $otherEmployee = Employee::factory()->create(['position' => 'employee', 'department_id' => $otherDepartment->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $otherEmployee->id]);
    Sanctum::actingAs($manager, ['leave-requests:update']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", [
        'status' => 'approved',
    ]);

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('reviewLeaveRequest_employeeToken_forbidden', function () {
    // Arrange
    $department = Department::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $colleague = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $colleague->id]);
    Sanctum::actingAs($employee, ['leave-requests:update']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", [
        'status' => 'approved',
    ]);

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('updateLeaveRequest_employeeApprovesOwnRequest_forbidden', function () {
    // Arrange - an employee must not be able to self-approve via the same endpoint a manager uses
    $employee = Employee::factory()->create(['position' => 'employee']);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    Sanctum::actingAs($employee, ['leave-requests:update']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", [
        'status' => 'approved',
    ]);

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('reviewLeaveRequest_alreadyReviewedRequest_forbidden', function () {
    // Arrange
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'status' => 'approved']);
    Sanctum::actingAs($manager, ['leave-requests:update']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", [
        'status' => 'rejected',
    ]);

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('reviewLeaveRequest_adminOtherDepartment_approved', function () {
    // Arrange
    $department = Department::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    Sanctum::actingAs(Employee::factory()->create(['position' => 'admin']), ['*']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", [
        'status' => 'approved',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'approved');
});

test('updateLeaveRequest_adminOverridesAlreadyReviewedRequestAnyDepartment_statusChanged', function () {
    // Arrange - admin can change the status of any leave request regardless of its current
    // status or the requester's department, unlike a manager who is limited to pending
    // requests within their own department.
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'approved',
        'reviewed_by' => $manager->id,
        'reviewed_at' => now(),
    ]);
    $admin = Employee::factory()->create(['position' => 'admin']);
    Sanctum::actingAs($admin, ['*']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", [
        'status' => 'rejected',
        'review_note' => 'Overturned after policy review',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.reviewer_name', $admin->name)
        ->assertJsonPath('data.review_note', 'Overturned after policy review');

    $this->assertDatabaseHas('leave_requests', [
        'id' => $leaveRequest->id,
        'status' => 'rejected',
        'reviewed_by' => $admin->id,
    ]);
});

test('updateLeaveRequest_adminRevertsToPending_reviewFieldsCleared', function () {
    // Arrange - only admins may send a request back to pending, and doing so should clear
    // any prior review so the request genuinely reads as unreviewed again.
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'approved',
        'reviewed_by' => $manager->id,
        'reviewed_at' => now(),
        'review_note' => 'Looks good',
    ]);
    Sanctum::actingAs(Employee::factory()->create(['position' => 'admin']), ['*']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", [
        'status' => 'pending',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.reviewed_by', null)
        ->assertJsonPath('data.reviewer_name', null)
        ->assertJsonPath('data.review_note', null);

    $this->assertDatabaseHas('leave_requests', [
        'id' => $leaveRequest->id,
        'status' => 'pending',
        'reviewed_by' => null,
        'review_note' => null,
    ]);
});

test('updateLeaveRequest_managerRevertsToPending_validationError', function () {
    // Arrange - non-admins must never be able to send a request back to pending
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'status' => 'approved']);
    Sanctum::actingAs($manager, ['leave-requests:update']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", [
        'status' => 'pending',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status'], 'errors');
});

test('cancelLeaveRequest_otherEmployeeRequest_forbidden', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    $otherEmployee = Employee::factory()->create(['position' => 'employee']);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $otherEmployee->id]);
    Sanctum::actingAs($employee, ['leave-requests:update']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", ['status' => 'cancelled']);

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('getLeaveRequest_managerOtherDepartment_forbidden', function () {
    // Arrange
    $managerDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $managerDepartment->id]);
    $otherEmployee = Employee::factory()->create(['position' => 'employee', 'department_id' => $otherDepartment->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $otherEmployee->id]);
    Sanctum::actingAs($manager, ['leave-requests:read']);

    // Act
    $response = $this->getJson("/api/leave-requests/{$leaveRequest->id}");

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});
