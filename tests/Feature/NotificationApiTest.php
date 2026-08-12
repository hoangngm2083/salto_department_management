<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Notifications\ApprovalStepActivated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Reuses $employee for every FK slot on the approval/step fixture (rather than letting
 * ApprovalRequestFactory/ApprovalStepFactory spin up their own default Employee::factory()
 * relations) - this helper is called in tight loops below, and each unnecessary extra
 * employee eats into Faker's shared unique-email pool across the full test suite run.
 */
function notifyEmployee(Employee $employee): void
{
    $approval = ApprovalRequest::factory()->create([
        'requestable_type' => Employee::class,
        'requestable_id' => $employee->id,
        'requested_by' => $employee->id,
        'subject_employee_id' => $employee->id,
    ]);
    $step = ApprovalStep::factory()->create([
        'approval_request_id' => $approval->id,
        'approver_employee_id' => $employee->id,
    ]);
    $employee->notify(new ApprovalStepActivated($approval, $step));
}

test('getNotifications_unreadCount_reflectsTrueTotalNotJustCurrentPage', function () {
    // Arrange
    $employee = Employee::factory()->create();
    foreach (range(1, 20) as $i) {
        notifyEmployee($employee);
    }
    Sanctum::actingAs($employee, ['notifications:read']);

    // Act
    $response = $this->getJson('/api/notifications?per_page=5');

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(5);
    expect($response->json('data.unread_count'))->toBe(20);
});

test('updateNotification_statusRead_marksAsRead', function () {
    // Arrange
    $employee = Employee::factory()->create();
    notifyEmployee($employee);
    $notification = $employee->notifications()->first();
    Sanctum::actingAs($employee, ['notifications:update']);

    // Act
    $response = $this->patchJson("/api/notifications/{$notification->id}", ['status' => 'read']);

    // Assert
    $response->assertSuccessful();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('updateNotification_statusUnread_marksAsUnread', function () {
    // Arrange
    $employee = Employee::factory()->create();
    notifyEmployee($employee);
    $notification = $employee->notifications()->first();
    $notification->markAsRead();
    Sanctum::actingAs($employee, ['notifications:update']);

    // Act
    $response = $this->patchJson("/api/notifications/{$notification->id}", ['status' => 'unread']);

    // Assert
    $response->assertSuccessful();
    expect($notification->fresh()->read_at)->toBeNull();
});

test('updateNotification_invalidStatusValue_validationError', function () {
    // Arrange
    $employee = Employee::factory()->create();
    notifyEmployee($employee);
    $notification = $employee->notifications()->first();
    Sanctum::actingAs($employee, ['notifications:update']);

    // Act
    $response = $this->patchJson("/api/notifications/{$notification->id}", ['status' => 'archived']);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status'], 'errors');
});

test('updateNotification_anotherUsersNotification_notFound', function () {
    // Arrange
    $owner = Employee::factory()->create();
    notifyEmployee($owner);
    $notification = $owner->notifications()->first();
    $intruder = Employee::factory()->create();
    Sanctum::actingAs($intruder, ['notifications:update']);

    // Act
    $response = $this->patchJson("/api/notifications/{$notification->id}", ['status' => 'read']);

    // Assert
    $response->assertNotFound();
});

test('updateManyNotifications_statusReadWithIds_marksListedOnesReadIgnoresUnowned', function () {
    // Arrange
    $employee = Employee::factory()->create();
    notifyEmployee($employee);
    notifyEmployee($employee);
    $ownNotificationIds = $employee->notifications()->pluck('id')->all();

    $otherEmployee = Employee::factory()->create();
    notifyEmployee($otherEmployee);
    $foreignNotification = $otherEmployee->notifications()->first();

    Sanctum::actingAs($employee, ['notifications:update']);

    // Act
    $response = $this->patchJson('/api/notifications', [
        'status' => 'read',
        'ids' => [...$ownNotificationIds, $foreignNotification->id],
    ]);

    // Assert
    $response->assertSuccessful();
    expect($employee->notifications()->whereNull('read_at')->count())->toBe(0);
    expect($foreignNotification->fresh()->read_at)->toBeNull();
});

test('updateManyNotifications_statusUnread_validationError', function () {
    // Arrange
    $employee = Employee::factory()->create();
    notifyEmployee($employee);
    Sanctum::actingAs($employee, ['notifications:update']);

    // Act
    $response = $this->patchJson('/api/notifications', [
        'status' => 'unread',
        'ids' => [$employee->notifications()->first()->id],
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status'], 'errors');
});

test('getNotifications_missingAbility_forbidden', function () {
    // Arrange
    Sanctum::actingAs(Employee::factory()->create(), []);

    // Act
    $response = $this->getJson('/api/notifications');

    // Assert
    $response->assertForbidden();
});
