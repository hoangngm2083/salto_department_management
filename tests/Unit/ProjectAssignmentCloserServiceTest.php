<?php

use App\Models\AssignmentRolePeriod;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Services\ProjectAssignmentCloserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('closeActiveAssignmentsForEmployee_activeAssignmentAndRolePeriod_bothEnded', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $project = Project::factory()->create();
    $assignment = ProjectAssignment::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'status' => 'active',
        'end_date' => null,
    ]);
    $rolePeriod = AssignmentRolePeriod::factory()->create([
        'project_assignment_id' => $assignment->id,
        'end_date' => null,
    ]);
    $closer = new ProjectAssignmentCloserService;

    // Act
    $closer->closeActiveAssignmentsForEmployee($employee);

    // Assert
    expect($assignment->fresh()->status->value)->toBe('ended')
        ->and($assignment->fresh()->end_date->toDateString())->toBe(today()->toDateString())
        ->and($rolePeriod->fresh()->end_date->toDateString())->toBe(today()->toDateString());
});

test('closeActiveAssignmentsForEmployee_alreadyEndedAssignment_untouched', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $project = Project::factory()->create();
    $assignment = ProjectAssignment::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'status' => 'ended',
        'end_date' => today()->subDays(5),
    ]);
    $closer = new ProjectAssignmentCloserService;

    // Act
    $closer->closeActiveAssignmentsForEmployee($employee);

    // Assert
    expect($assignment->fresh()->end_date->toDateString())->toBe(today()->subDays(5)->toDateString());
});

test('closeActiveAssignmentsForEmployee_noActiveAssignments_noop', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $closer = new ProjectAssignmentCloserService;

    // Act / Assert (should not throw)
    $closer->closeActiveAssignmentsForEmployee($employee);

    expect(true)->toBeTrue();
});
