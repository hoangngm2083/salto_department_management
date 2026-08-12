<?php

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectManager;
use App\Services\ProjectManagerGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('projectsLeftWithoutManagerIfRemoved_soleActiveManager_projectNameReturned', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $project = Project::factory()->create(['name' => 'Solo Managed']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'end_date' => null]);
    $guard = new ProjectManagerGuardService;

    // Act
    $result = $guard->projectsLeftWithoutManagerIfRemoved($employee);

    // Assert
    expect($result)->toBe(['Solo Managed']);
});

test('projectsLeftWithoutManagerIfRemoved_otherActiveManagerExists_emptyArray', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $otherManager = Employee::factory()->create();
    $project = Project::factory()->create(['name' => 'Co Managed']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'end_date' => null]);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $otherManager->id, 'end_date' => null]);
    $guard = new ProjectManagerGuardService;

    // Act
    $result = $guard->projectsLeftWithoutManagerIfRemoved($employee);

    // Assert
    expect($result)->toBe([]);
});

test('projectsLeftWithoutManagerIfRemoved_managerRoleAlreadyEnded_notCounted', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $project = Project::factory()->create(['name' => 'Past Managed']);
    ProjectManager::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'end_date' => today()->subDay(),
    ]);
    $guard = new ProjectManagerGuardService;

    // Act
    $result = $guard->projectsLeftWithoutManagerIfRemoved($employee);

    // Assert
    expect($result)->toBe([]);
});

test('projectsLeftWithoutManagerIfRemoved_soleOnOneProjectAndSharedOnAnother_onlySoleOneReturned', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $otherManager = Employee::factory()->create();
    $soleProject = Project::factory()->create(['name' => 'Sole Project']);
    $sharedProject = Project::factory()->create(['name' => 'Shared Project']);

    ProjectManager::factory()->create(['project_id' => $soleProject->id, 'employee_id' => $employee->id, 'end_date' => null]);
    ProjectManager::factory()->create(['project_id' => $sharedProject->id, 'employee_id' => $employee->id, 'end_date' => null]);
    ProjectManager::factory()->create(['project_id' => $sharedProject->id, 'employee_id' => $otherManager->id, 'end_date' => null]);

    $guard = new ProjectManagerGuardService;

    // Act
    $result = $guard->projectsLeftWithoutManagerIfRemoved($employee);

    // Assert
    expect($result)->toBe(['Sole Project']);
});

test('projectsLeftWithoutManagerIfRemoved_notAManager_emptyArray', function () {
    // Arrange
    $employee = Employee::factory()->create();
    Project::factory()->create();
    $guard = new ProjectManagerGuardService;

    // Act
    $result = $guard->projectsLeftWithoutManagerIfRemoved($employee);

    // Assert
    expect($result)->toBe([]);
});
