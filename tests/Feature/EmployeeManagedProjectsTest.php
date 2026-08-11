<?php

use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectManager;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $adminDepartment = Department::factory()->create([
        'name' => 'Admin Department',
        'slug' => 'admin-department',
        'status' => 'active',
    ]);

    $this->admin = Employee::factory()->create([
        'position' => 'admin',
        'department_id' => $adminDepartment->id,
    ]);

    Sanctum::actingAs($this->admin, ['*']);
});

test('managedProjects_activeManagerWithTasks_returnsProjectWithCounts', function () {
    // Arrange - PM-ness isn't tied to system role, so a plain employee here is deliberate.
    $employee = Employee::factory()->create(['position' => 'employee']);
    $project = Project::factory()->create(['name' => 'Intern']);
    ProjectManager::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'end_date' => null,
    ]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Done]);
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => today()->subDay(),
    ]);

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}/managed-projects");

    // Assert
    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Intern')
        ->assertJsonPath('data.0.total_count', 2)
        ->assertJsonPath('data.0.done_count', 1)
        ->assertJsonPath('data.0.overdue_count', 1);
});

test('managedProjects_endedManagerRelation_excluded', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $project = Project::factory()->create();
    ProjectManager::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'end_date' => today()->subDay(),
    ]);

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}/managed-projects");

    // Assert
    $response->assertSuccessful()->assertJsonCount(0, 'data');
});

test('managedProjects_employeeNotAManager_returnsEmpty', function () {
    // Arrange
    $employee = Employee::factory()->create();

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}/managed-projects");

    // Assert
    $response->assertSuccessful()->assertJsonCount(0, 'data');
});

test('managedProjects_selfService_employeeViewsOwn', function () {
    // Arrange
    $department = Department::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id]);
    Sanctum::actingAs($employee, ['*']);

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}/managed-projects");

    // Assert
    $response->assertSuccessful()->assertJsonCount(1, 'data');
});

test('managedProjects_multipleProjects_managersEagerLoadedWithoutNPlusOne', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    Project::factory()->count(4)->create()->each(
        fn ($project) => ProjectManager::factory()->create([
            'project_id' => $project->id,
            'employee_id' => $employee->id,
            'end_date' => null,
        ])
    );

    // Act - without ->with('managers.employee') this was O(2N+1): 1 main query
    // + 1 per project for `managers` + 1 per project for `managers.employee`
    // (9 queries for these 4 projects). Eager-loaded, it stays flat regardless of N.
    DB::enableQueryLog();
    $response = $this->getJson("/api/employees/{$employee->id}/managed-projects");
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Assert
    $response->assertSuccessful()->assertJsonCount(4, 'data');
    expect($queryCount)->toBeLessThanOrEqual(5);
});

test('managedProjects_employeeViewsUnrelatedEmployee_forbidden', function () {
    // Arrange
    $viewer = Employee::factory()->create(['position' => 'employee']);
    $other = Employee::factory()->create(['position' => 'employee']);
    Sanctum::actingAs($viewer, ['*']);

    // Act
    $response = $this->getJson("/api/employees/{$other->id}/managed-projects");

    // Assert
    $response->assertForbidden();
});
