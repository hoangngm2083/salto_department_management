<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);

test('login_validCredentials_bearerToken', function () {
    // Arrange
    $employee = Employee::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'position' => 'admin',
    ]);

    // Act
    $response = $this->postJson('/api/auth/login', [
        'email' => $employee->email,
        'password' => 'password',
        'device_name' => 'pest',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Authenticated successfully.')
        ->assertJsonPath('data.employee.id', $employee->id)
        ->assertJsonPath('data.token_type', 'Bearer');

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

test('login_managerCredentials_abilitiesPersisted', function () {
    // Arrange
    $manager = Employee::factory()->create([
        'email' => 'manager@example.com',
        'password' => 'password',
        'position' => 'manager',
    ]);

    // Act
    $response = $this->postJson('/api/auth/login', [
        'email' => $manager->email,
        'password' => 'password',
        'device_name' => 'pest',
    ]);

    // Assert
    $response->assertSuccessful();

    expect($manager->tokens()->latest('id')->firstOrFail()->abilities)->toBe([
        'profile:read',
        'employees:read',
        'employees:create',
        'employees:update',
        'departments:read',
        'departments:update',
        'levels:read',
        'projects:read',
        'projects:manage-assignments',
        'project-roles:read',
        'leave-requests:read',
        'leave-requests:create',
        'leave-requests:update',
        'notifications:read',
        'notifications:update',
    ]);
});

test('login_employeeCredentials_abilitiesPersisted', function () {
    // Arrange
    $employee = Employee::factory()->create([
        'email' => 'employee@example.com',
        'password' => 'password',
        'position' => 'employee',
    ]);

    // Act
    $response = $this->postJson('/api/auth/login', [
        'email' => $employee->email,
        'password' => 'password',
        'device_name' => 'pest',
    ]);

    // Assert
    $response->assertSuccessful();

    expect($employee->tokens()->latest('id')->firstOrFail()->abilities)->toBe([
        'profile:read',
        'employees:read',
        'employees:update',
        'projects:read',
        'leave-requests:read',
        'leave-requests:create',
        'leave-requests:update',
        'notifications:read',
        'notifications:update',
    ]);
});

test('login_invalidCredentials_unauthorized', function () {
    // Arrange
    $employee = Employee::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    // Act
    $response = $this->postJson('/api/auth/login', [
        'email' => $employee->email,
        'password' => 'incorrect-password',
        'device_name' => 'pest',
    ]);

    // Assert
    $response->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Unauthenticated.');
});

test('login_missingCredentials_validationErrors', function () {
    // Arrange
    $payload = [];

    // Act
    $response = $this->postJson('/api/auth/login', $payload);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['email', 'password', 'device_name'], 'errors');
});

test('getCurrentEmployee_authenticatedEmployee_employeeReturned', function () {
    // Arrange
    $employee = Employee::factory()->create();
    Sanctum::actingAs($employee, ['profile:read']);

    // Act
    $response = $this->getJson('/api/auth/me');

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $employee->id)
        ->assertJsonPath('data.email', $employee->email);
});

test('getCurrentEmployee_missingToken_unauthorized', function () {
    // Arrange
    $endpoint = '/api/auth/me';

    // Act
    $response = $this->getJson($endpoint);

    // Assert
    $response->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Unauthenticated.');
});

test('logout_validToken_currentTokenRevoked', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $token = $employee->createToken('pest', ['profile:read'])->plainTextToken;

    // Act
    $response = $this->withToken($token)->postJson('/api/auth/logout');

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logged out successfully.');

    expect($employee->tokens()->exists())->toBeFalse();
});
