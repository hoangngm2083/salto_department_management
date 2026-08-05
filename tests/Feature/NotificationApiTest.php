<?php

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Notifications\LeaveRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function notifyEmployee(Employee $employee): void
{
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id]);
    $employee->notify(new LeaveRequestSubmitted($leaveRequest));
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
