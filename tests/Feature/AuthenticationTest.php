<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);

test('login_valid_credentials_returns_bearer_token', function () {
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

test('login_invalid_credentials_returns_unauthorized', function () {
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

test('login_missing_credentials_returns_validation_errors', function () {
    // Arrange
    $payload = [];

    // Act
    $response = $this->postJson('/api/auth/login', $payload);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['email', 'password', 'device_name'], 'errors');
});

test('me_authenticated_employee_returns_employee', function () {
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

test('me_missing_token_returns_unauthorized', function () {
    // Arrange
    $endpoint = '/api/auth/me';

    // Act
    $response = $this->getJson($endpoint);

    // Assert
    $response->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Unauthenticated.');
});

test('logout_valid_token_revokes_current_token', function () {
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
