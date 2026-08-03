<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('createLeaveRequest_validPayload_created', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    Sanctum::actingAs($employee, ['leave-requests:create']);

    $payload = [
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-03',
        'reason' => 'Family trip',
    ];

    // Act
    $response = $this->postJson('/api/leave-requests', $payload);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Leave request submitted successfully.')
        ->assertJsonPath('data.employee_id', $employee->id)
        ->assertJsonPath('data.start_date', '2026-09-01')
        ->assertJsonPath('data.end_date', '2026-09-03')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('leave_requests', [
        'employee_id' => $employee->id,
        'status' => 'pending',
    ]);
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
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['end_date'], 'errors');
});

test('createLeaveRequest_missingRequiredFields_validationError', function () {
    // Arrange
    Sanctum::actingAs(Employee::factory()->create(), ['leave-requests:create']);

    // Act
    $response = $this->postJson('/api/leave-requests', []);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date', 'end_date', 'reason'], 'errors');
});

test('getLeaveRequests_employee_scopedToOwn', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    $otherEmployee = Employee::factory()->create(['position' => 'employee']);
    LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    LeaveRequest::factory()->create(['employee_id' => $otherEmployee->id]);
    Sanctum::actingAs($employee, ['leave-requests:read']);

    // Act
    $response = $this->getJson('/api/leave-requests');

    // Assert
    $response->assertSuccessful();

    $employeeIds = collect($response->json('data.data'))->pluck('employee_id')->unique()->values()->all();

    expect($employeeIds)->toBe([$employee->id]);
});

test('getLeaveRequests_manager_scopedToDepartment', function () {
    // Arrange
    $managerDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $managerDepartment->id]);
    $ownTeamEmployee = Employee::factory()->create(['position' => 'employee', 'department_id' => $managerDepartment->id]);
    $otherTeamEmployee = Employee::factory()->create(['position' => 'employee', 'department_id' => $otherDepartment->id]);
    LeaveRequest::factory()->create(['employee_id' => $ownTeamEmployee->id]);
    LeaveRequest::factory()->create(['employee_id' => $otherTeamEmployee->id]);
    Sanctum::actingAs($manager, ['leave-requests:read']);

    // Act
    $response = $this->getJson('/api/leave-requests');

    // Assert
    $response->assertSuccessful();

    $employeeIds = collect($response->json('data.data'))->pluck('employee_id')->unique()->values()->all();

    expect($employeeIds)->toBe([$ownTeamEmployee->id]);
});

test('getLeaveRequests_admin_allReturned', function () {
    // Arrange
    LeaveRequest::factory()->count(2)->create();
    Sanctum::actingAs(Employee::factory()->create(['position' => 'admin']), ['*']);

    // Act
    $response = $this->getJson('/api/leave-requests');

    // Assert
    $response->assertSuccessful();

    expect($response->json('data.data'))->toHaveCount(2);
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

test('cancelLeaveRequest_ownPendingRequest_cancelled', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    Sanctum::actingAs($employee, ['leave-requests:update']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", ['status' => 'cancelled']);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('leave_requests', [
        'id' => $leaveRequest->id,
        'status' => 'cancelled',
    ]);
});

test('cancelLeaveRequest_alreadyApprovedRequest_forbidden', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'status' => 'approved']);
    Sanctum::actingAs($employee, ['leave-requests:update']);

    // Act
    $response = $this->patchJson("/api/leave-requests/{$leaveRequest->id}", ['status' => 'cancelled']);

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});
