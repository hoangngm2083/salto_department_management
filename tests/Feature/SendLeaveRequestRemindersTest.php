<?php

use App\Enums\LeaveRequestStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Notifications\LeaveRequestReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('leaveRequestReminders_isScheduled_withConfiguredCronAndOverlapProtection', function () {
    $schedule = app(Schedule::class);
    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command, 'leave-requests:send-reminders'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe(config('leave-requests.reminder_cron'))
        ->and($event->withoutOverlapping)->toBeTrue();
});

test('sendLeaveRequestReminders_pendingWithinWindowNotYetReminded_sentAndMarked', function () {
    // Arrange
    Notification::fake();
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => LeaveRequestStatus::Pending,
        'start_date' => today()->addDay(),
        'reminder_sent_at' => null,
    ]);

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertSentTo($manager, LeaveRequestReminder::class);
    expect($leaveRequest->fresh()->reminder_sent_at)->not->toBeNull();
});

test('sendLeaveRequestReminders_startDateOutsideWindow_notSent', function () {
    // Arrange
    Notification::fake();
    $department = Department::factory()->create();
    Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => LeaveRequestStatus::Pending,
        'start_date' => today()->addDays(10),
        'reminder_sent_at' => null,
    ]);

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertNothingSent();
    expect($leaveRequest->fresh()->reminder_sent_at)->toBeNull();
});

test('sendLeaveRequestReminders_alreadyReminded_notSentAgain', function () {
    // Arrange
    Notification::fake();
    $department = Department::factory()->create();
    Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => LeaveRequestStatus::Pending,
        'start_date' => today()->addDay(),
        'reminder_sent_at' => now()->subHour(),
    ]);

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertNothingSent();
});

test('sendLeaveRequestReminders_nonPendingStatus_notSent', function () {
    // Arrange
    Notification::fake();
    $department = Department::factory()->create();
    Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => LeaveRequestStatus::Approved,
        'start_date' => today()->addDay(),
        'reminder_sent_at' => null,
    ]);

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertNothingSent();
});

test('sendLeaveRequestReminders_departmentHasNoManager_marksSentWithoutErrorOrNotification', function () {
    // Arrange
    Notification::fake();
    $department = Department::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => LeaveRequestStatus::Pending,
        'start_date' => today()->addDay(),
        'reminder_sent_at' => null,
    ]);

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertNothingSent();
    expect($leaveRequest->fresh()->reminder_sent_at)->not->toBeNull();
});

test('sendLeaveRequestReminders_customWindowFromConfig_respected', function () {
    // Arrange
    config(['leave-requests.reminder_days_before_start' => 5]);
    Notification::fake();
    $department = Department::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id]);
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => LeaveRequestStatus::Pending,
        'start_date' => today()->addDays(4),
        'reminder_sent_at' => null,
    ]);

    // Act
    $this->artisan('leave-requests:send-reminders')->assertSuccessful();

    // Assert
    Notification::assertSentTo($manager, LeaveRequestReminder::class);
});
