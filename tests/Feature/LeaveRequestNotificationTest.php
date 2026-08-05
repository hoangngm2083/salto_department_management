<?php

use App\Enums\LeaveRequestStatus;
use App\Events\LeaveRequestReviewed;
use App\Events\LeaveRequestSubmitted;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Notifications\LeaveRequestReviewed as LeaveRequestReviewedNotification;
use App\Notifications\LeaveRequestSubmitted as LeaveRequestSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('createLeaveRequest_validPayload_dispatchesSubmittedEvent', function () {
    // Arrange
    Event::fake([LeaveRequestSubmitted::class]);
    $employee = Employee::factory()->create(['position' => 'employee']);
    Sanctum::actingAs($employee, ['leave-requests:create']);

    // Act
    $this->postJson('/api/leave-requests', [
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-03',
        'reason' => 'Family trip',
    ])->assertCreated();

    // Assert
    Event::assertDispatched(LeaveRequestSubmitted::class, fn ($event) => $event->leaveRequest->employee_id === $employee->id);
});

test('updateLeaveRequestStatus_approved_dispatchesReviewedEvent', function () {
    // Arrange
    Event::fake([LeaveRequestReviewed::class]);
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    Sanctum::actingAs($manager, ['leave-requests:update']);

    // Act
    $this->patchJson("/api/leave-requests/{$leaveRequest->id}", ['status' => 'approved'])->assertSuccessful();

    // Assert
    Event::assertDispatched(LeaveRequestReviewed::class, fn ($event) => $event->leaveRequest->id === $leaveRequest->id);
});

test('updateLeaveRequestStatus_cancelled_doesNotDispatchReviewedEvent', function () {
    // Arrange
    Event::fake([LeaveRequestReviewed::class]);
    $employee = Employee::factory()->create(['position' => 'employee']);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    Sanctum::actingAs($employee, ['leave-requests:update']);

    // Act
    $this->patchJson("/api/leave-requests/{$leaveRequest->id}", ['status' => 'cancelled'])->assertSuccessful();

    // Assert
    Event::assertNotDispatched(LeaveRequestReviewed::class);
});

test('createLeaveRequest_departmentHasManager_managerNotified', function () {
    // Arrange
    Notification::fake();
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    Sanctum::actingAs($employee, ['leave-requests:create']);

    // Act
    $this->postJson('/api/leave-requests', [
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-03',
        'reason' => 'Family trip',
    ])->assertCreated();

    // Assert
    Notification::assertSentTo($manager, LeaveRequestSubmittedNotification::class);
    Notification::assertNotSentTo($employee, LeaveRequestSubmittedNotification::class);
});

test('createLeaveRequest_departmentHasNoManager_nothingSentNoError', function () {
    // Arrange
    Notification::fake();
    Log::spy();
    $department = Department::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    Sanctum::actingAs($employee, ['leave-requests:create']);

    // Act
    $response = $this->postJson('/api/leave-requests', [
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-03',
        'reason' => 'Solo trip',
    ]);

    // Assert
    $response->assertCreated();
    Notification::assertNothingSent();
    Log::shouldHaveReceived('warning')->once();
});

test('updateLeaveRequestStatus_approved_employeeNotified', function () {
    // Arrange
    Notification::fake();
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    Sanctum::actingAs($manager, ['leave-requests:update']);

    // Act
    $this->patchJson("/api/leave-requests/{$leaveRequest->id}", ['status' => 'approved'])->assertSuccessful();

    // Assert
    Notification::assertSentTo(
        $employee,
        LeaveRequestReviewedNotification::class,
        fn ($notification) => $notification->leaveRequest->status === LeaveRequestStatus::Approved
    );
});

test('updateLeaveRequestStatus_rejected_employeeNotified', function () {
    // Arrange
    Notification::fake();
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    Sanctum::actingAs($manager, ['leave-requests:update']);

    // Act
    $this->patchJson("/api/leave-requests/{$leaveRequest->id}", [
        'status' => 'rejected',
        'review_note' => 'Understaffed that week',
    ])->assertSuccessful();

    // Assert
    Notification::assertSentTo(
        $employee,
        LeaveRequestReviewedNotification::class,
        fn ($notification) => $notification->leaveRequest->status === LeaveRequestStatus::Rejected
    );
});

test('cancelLeaveRequest_ownPendingRequest_employeeNotNotified', function () {
    // Arrange
    Notification::fake();
    $employee = Employee::factory()->create(['position' => 'employee']);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    Sanctum::actingAs($employee, ['leave-requests:update']);

    // Act
    $this->patchJson("/api/leave-requests/{$leaveRequest->id}", ['status' => 'cancelled'])->assertSuccessful();

    // Assert
    Notification::assertNothingSent();
});

test('updateLeaveRequestStatus_adminResetToPending_employeeNotNotified', function () {
    // Arrange
    Notification::fake();
    $employee = Employee::factory()->create(['position' => 'employee']);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'status' => 'approved']);
    Sanctum::actingAs(Employee::factory()->create(['position' => 'admin']), ['*']);

    // Act
    $this->patchJson("/api/leave-requests/{$leaveRequest->id}", ['status' => 'pending'])->assertSuccessful();

    // Assert
    Notification::assertNothingSent();
});
