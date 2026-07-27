<?php

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $adminDepartment = Department::factory()->create([
        'name' => 'Admin Department',
        'slug' => 'admin-department',
        'status' => 'active',
    ]);

    Sanctum::actingAs(
        Employee::factory()->make([
            'position' => 'admin',
            'department_id' => $adminDepartment->id,
        ]),
        ['*']
    );
});

test('getAllDepartments_defaultParam_onlyActive', function () {
    // Arrange
    $initialActiveCount = Department::where('status', 'active')->count();

    Department::factory()->create([
        'name' => 'Active Dept',
        'slug' => 'active-dept',
        'status' => 'active',
    ]);
    Department::factory()->create([
        'name' => 'Inactive Dept',
        'slug' => 'inactive-dept',
        'status' => 'inactive',
    ]);

    // Act
    $response = $this->getJson('/api/departments');

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Departments retrieved successfully.');

    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->values()->all();

    expect($statuses)->toBe(['active'])
        ->and($response->json('data.data'))->toHaveCount($initialActiveCount + 1);
});

test('getAllDepartments_statusActive_onlyActive', function () {
    // Arrange
    $initialActiveCount = Department::where('status', 'active')->count();

    Department::factory()->create(['name' => 'Active A', 'slug' => 'active-a', 'status' => 'active']);
    Department::factory()->create(['name' => 'Inactive A', 'slug' => 'inactive-a', 'status' => 'inactive']);

    // Act
    $response = $this->getJson('/api/departments?status=active');

    // Assert
    $response->assertSuccessful();

    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->values()->all();

    expect($statuses)->toBe(['active'])
        ->and($response->json('data.data'))->toHaveCount($initialActiveCount + 1);
});

test('getAllDepartments_statusInactive_onlyInactive', function () {
    // Arrange
    $initialInactiveCount = Department::where('status', 'inactive')->count();

    Department::factory()->create(['name' => 'Active B', 'slug' => 'active-b', 'status' => 'active']);
    Department::factory()->create(['name' => 'Inactive B', 'slug' => 'inactive-b', 'status' => 'inactive']);

    // Act
    $response = $this->getJson('/api/departments?status=inactive');

    // Assert
    $response->assertSuccessful();

    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->values()->all();

    expect($statuses)->toBe(['inactive'])
        ->and($response->json('data.data'))->toHaveCount($initialInactiveCount + 1);
});

test('getAllDepartments_statusAll_allStatuses', function () {
    // Arrange
    $initialCount = Department::count();

    Department::factory()->create(['name' => 'Active C', 'slug' => 'active-c', 'status' => 'active']);
    Department::factory()->create(['name' => 'Inactive C', 'slug' => 'inactive-c', 'status' => 'inactive']);

    // Act
    $response = $this->getJson('/api/departments?status=all');

    // Assert
    $response->assertSuccessful();

    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->sort()->values()->all();

    expect($statuses)->toBe(['active', 'inactive'])
        ->and($response->json('data.data'))->toHaveCount($initialCount + 2);
});

test('getAllDepartments_invalidStatus_returns422', function () {
    // Arrange / Act
    $response = $this->getJson('/api/departments?status=unknown');

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['status'], 'errors');
});

test('createDepartment_validPayload_created', function () {
    // Arrange
    $payload = [
        'name' => 'Engineering',
        'slug' => 'engineering',
        'description' => 'Builds products',
        'status' => 'active',
    ];

    // Act
    $response = $this->postJson('/api/departments', $payload);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Department created successfully.')
        ->assertJsonPath('data.name', 'Engineering')
        ->assertJsonPath('data.slug', 'engineering')
        ->assertJsonPath('data.description', 'Builds products')
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('departments', [
        'name' => 'Engineering',
        'slug' => 'engineering',
        'status' => 'active',
    ]);
});

test('createDepartment_withoutSlug_autoGeneratesSlug', function () {
    // Arrange
    $payload = [
        'name' => 'Human Resources',
        'description' => 'People ops',
        'status' => 'active',
    ];

    // Act
    $response = $this->postJson('/api/departments', $payload);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.name', 'Human Resources')
        ->assertJsonPath('data.slug', 'human-resources');

    $this->assertDatabaseHas('departments', [
        'name' => 'Human Resources',
        'slug' => 'human-resources',
    ]);
});

test('createDepartment_withoutName_returns422', function () {
    // Arrange / Act
    $response = $this->postJson('/api/departments', [
        'description' => 'No name provided',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['name'], 'errors');
});

test('createDepartment_duplicateSlug_returns422', function () {
    // Arrange
    Department::factory()->create([
        'name' => 'Existing',
        'slug' => 'taken-slug',
        'status' => 'active',
    ]);

    // Act
    $response = $this->postJson('/api/departments', [
        'name' => 'Another Dept',
        'slug' => 'taken-slug',
        'status' => 'active',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug'], 'errors');
});

test('getDepartment_existingSlug_returnsDepartment', function () {
    // Arrange
    $department = Department::factory()->create([
        'name' => 'Finance',
        'slug' => 'finance',
        'description' => 'Money matters',
        'status' => 'active',
    ]);

    // Act
    $response = $this->getJson("/api/departments/{$department->slug}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Department retrieved successfully.')
        ->assertJsonPath('data.id', $department->id)
        ->assertJsonPath('data.name', 'Finance')
        ->assertJsonPath('data.slug', 'finance')
        ->assertJsonPath('data.description', 'Money matters')
        ->assertJsonPath('data.status', 'active');
});

test('getDepartment_unknownSlug_returns404', function () {
    // Arrange / Act
    $response = $this->getJson('/api/departments/does-not-exist');

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Resource not found.');
});

test('updateDepartment_validPayload_updated', function () {
    // Arrange
    $department = Department::factory()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
        'description' => 'Old description',
        'status' => 'active',
    ]);

    $payload = [
        'name' => 'New Name',
        'slug' => 'new-name',
        'description' => 'New description',
        'status' => 'inactive',
    ];

    // Act
    $response = $this->putJson("/api/departments/{$department->slug}", $payload);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Department updated successfully.')
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.slug', 'new-name')
        ->assertJsonPath('data.description', 'New description')
        ->assertJsonPath('data.status', 'inactive');

    $this->assertDatabaseHas('departments', [
        'id' => $department->id,
        'name' => 'New Name',
        'slug' => 'new-name',
        'status' => 'inactive',
    ]);
});

test('updateDepartment_keepsOwnSlug_succeeds', function () {
    // Arrange
    $department = Department::factory()->create([
        'name' => 'Keep Slug',
        'slug' => 'keep-slug',
        'status' => 'active',
    ]);

    // Act
    $response = $this->putJson("/api/departments/{$department->slug}", [
        'name' => 'Updated Keep Slug',
        'slug' => 'keep-slug',
        'status' => 'active',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.slug', 'keep-slug')
        ->assertJsonPath('data.name', 'Updated Keep Slug');
});

test('deleteDepartment_existingSlug_softDeleted', function () {
    // Arrange
    $department = Department::factory()->create([
        'name' => 'To Delete',
        'slug' => 'to-delete',
        'status' => 'active',
    ]);

    // Act
    $response = $this->deleteJson("/api/departments/{$department->slug}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Department deleted successfully.')
        ->assertJsonPath('data', null);

    $this->assertSoftDeleted($department);
});

test('getDepartment_deletedSlug_returns404', function () {
    // Arrange
    $department = Department::factory()->create([
        'name' => 'Already Deleted',
        'slug' => 'already-deleted',
        'status' => 'active',
    ]);
    $department->delete();

    // Act
    $response = $this->getJson("/api/departments/{$department->slug}");

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false);
});
