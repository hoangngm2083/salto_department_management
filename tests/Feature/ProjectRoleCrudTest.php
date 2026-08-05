<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\ProjectRole;
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

test('getProjectRoles_defaultStatus_activeProjectRolesReturned', function () {
    // Arrange
    $initialActiveCount = ProjectRole::where('status', 'active')->count();

    ProjectRole::factory()->create(['name' => 'Backend', 'slug' => 'backend', 'status' => 'active']);
    ProjectRole::factory()->create(['name' => 'QA', 'slug' => 'qa', 'status' => 'inactive']);

    // Act
    $response = $this->getJson('/api/project-roles');

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Project roles retrieved successfully.');

    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->values()->all();

    expect($statuses)->toBe(['active'])
        ->and($response->json('data.data'))->toHaveCount($initialActiveCount + 1);
});

test('getProjectRoles_allStatus_allProjectRolesReturned', function () {
    // Arrange
    $initialCount = ProjectRole::count();

    ProjectRole::factory()->create(['name' => 'Frontend', 'slug' => 'frontend', 'status' => 'active']);
    ProjectRole::factory()->create(['name' => 'DevOps', 'slug' => 'devops', 'status' => 'inactive']);

    // Act
    $response = $this->getJson('/api/project-roles?status=all');

    // Assert
    $response->assertSuccessful();

    expect($response->json('data.data'))->toHaveCount($initialCount + 2);
});

test('getProjectRoles_invalidStatus_validationError', function () {
    // Arrange / Act
    $response = $this->getJson('/api/project-roles?status=unknown');

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status'], 'errors');
});

test('createProjectRole_validPayload_created', function () {
    // Arrange
    $payload = [
        'name' => 'Solution Architect',
        'description' => 'Owns technical architecture decisions',
        'status' => 'active',
    ];

    // Act
    $response = $this->postJson('/api/project-roles', $payload);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Project role created successfully.')
        ->assertJsonPath('data.name', 'Solution Architect')
        ->assertJsonPath('data.slug', 'solution-architect')
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('project_roles', ['name' => 'Solution Architect', 'slug' => 'solution-architect']);
});

test('createProjectRole_missingName_validationError', function () {
    // Arrange / Act
    $response = $this->postJson('/api/project-roles', []);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name'], 'errors');
});

test('createProjectRole_duplicateSlug_validationError', function () {
    // Arrange
    ProjectRole::factory()->create(['name' => 'Existing', 'slug' => 'existing']);

    // Act
    $response = $this->postJson('/api/project-roles', [
        'name' => 'Existing',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug'], 'errors');
});

test('getProjectRole_existingSlug_projectRoleReturned', function () {
    // Arrange
    $projectRole = ProjectRole::factory()->create(['name' => 'Tech Lead', 'slug' => 'tech-lead']);

    // Act
    $response = $this->getJson("/api/project-roles/{$projectRole->slug}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.id', $projectRole->id)
        ->assertJsonPath('data.name', 'Tech Lead');
});

test('getProjectRole_unknownSlug_notFound', function () {
    // Arrange / Act
    $response = $this->getJson('/api/project-roles/does-not-exist');

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false);
});

test('updateProjectRole_validPayload_updated', function () {
    // Arrange
    $projectRole = ProjectRole::factory()->create(['name' => 'Old Name', 'slug' => 'old-name', 'status' => 'active']);

    // Act
    $response = $this->putJson("/api/project-roles/{$projectRole->slug}", [
        'name' => 'New Name',
        'status' => 'inactive',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.slug', 'new-name')
        ->assertJsonPath('data.status', 'inactive');
});

test('deleteProjectRole_existingSlug_softDeleted', function () {
    // Arrange
    $projectRole = ProjectRole::factory()->create(['name' => 'To Delete', 'slug' => 'to-delete']);

    // Act
    $response = $this->deleteJson("/api/project-roles/{$projectRole->slug}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Project role deleted successfully.');

    $this->assertSoftDeleted($projectRole);
});
