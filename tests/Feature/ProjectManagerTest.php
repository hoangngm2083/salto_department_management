<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectManager;
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

test('addProjectManager_activeEmployee_added', function () {
    // Arrange
    $manager = Employee::factory()->create(['status' => 'active']);
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id]);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/managers", [
        'employee_id' => $manager->id,
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Project manager added successfully.')
        ->assertJsonPath('data.employee_id', $manager->id)
        ->assertJsonPath('data.end_date', null);

    $this->assertDatabaseHas('project_managers', [
        'project_id' => $project->id,
        'employee_id' => $manager->id,
        'end_date' => null,
    ]);
});

test('addProjectManager_alreadyActiveManager_validationError', function () {
    // Arrange
    $project = Project::factory()->create();
    $manager = Employee::factory()->create(['status' => 'active']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $manager->id, 'end_date' => null]);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/managers", [
        'employee_id' => $manager->id,
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id'], 'errors');
});

test('addProjectManager_inactiveEmployee_validationError', function () {
    // Arrange
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id]);
    $inactiveEmployee = Employee::factory()->create(['status' => 'inactive']);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/managers", [
        'employee_id' => $inactiveEmployee->id,
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id'], 'errors');
});

test('removeProjectManager_otherActiveManagersRemain_ended', function () {
    // Arrange
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id]);
    $toRemove = ProjectManager::factory()->create(['project_id' => $project->id, 'end_date' => null]);

    // Act
    $response = $this->deleteJson("/api/projects/{$project->slug}/managers/{$toRemove->id}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Project manager removed successfully.');

    expect($toRemove->fresh()->end_date->toDateString())->toBe(today()->toDateString());
});

test('removeProjectManager_lastActiveManagerWithoutReplacement_validationError', function () {
    // Arrange
    $project = Project::factory()->create();
    $lastManager = ProjectManager::factory()->create(['project_id' => $project->id, 'end_date' => null]);

    // Act
    $response = $this->deleteJson("/api/projects/{$project->slug}/managers/{$lastManager->id}");

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['replacement_employee_id'], 'errors');

    $this->assertDatabaseHas('project_managers', ['id' => $lastManager->id, 'end_date' => null]);
});

test('removeProjectManager_lastActiveManagerWithReplacement_replaced', function () {
    // Arrange
    $project = Project::factory()->create();
    $lastManager = ProjectManager::factory()->create(['project_id' => $project->id, 'end_date' => null]);
    $replacement = Employee::factory()->create(['status' => 'active']);

    // Act
    $response = $this->deleteJson("/api/projects/{$project->slug}/managers/{$lastManager->id}", [
        'replacement_employee_id' => $replacement->id,
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($lastManager->fresh()->end_date->toDateString())->toBe(today()->toDateString());
    $this->assertDatabaseHas('project_managers', [
        'project_id' => $project->id,
        'employee_id' => $replacement->id,
        'end_date' => null,
    ]);

    $activeManagerIds = collect($response->json('data.managers'))
        ->whereNull('end_date')
        ->pluck('employee_id')
        ->all();

    expect($activeManagerIds)->toBe([$replacement->id]);
});

test('removeProjectManager_notBelongingToProject_notFound', function () {
    // Arrange
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $projectA->id]);
    $managerOfB = ProjectManager::factory()->create(['project_id' => $projectB->id]);

    // Act
    $response = $this->deleteJson("/api/projects/{$projectA->slug}/managers/{$managerOfB->id}");

    // Assert
    $response->assertNotFound();
});
