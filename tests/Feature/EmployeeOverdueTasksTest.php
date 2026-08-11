<?php

use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectManager;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

test('overdueManagedTasks_activeManagerWithOverdueTasks_returnsOverdueOnly', function () {
    // Arrange - PM-ness isn't tied to system role, so a plain employee here is deliberate.
    $employee = Employee::factory()->create(['position' => 'employee']);
    $project = Project::factory()->create(['name' => 'Intern']);
    ProjectManager::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'end_date' => null,
    ]);
    $overdue = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => today()->subDays(2),
    ]);
    // Not overdue: still in the future.
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => today()->addDay(),
    ]);
    // Not overdue: no due date at all.
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'due_date' => null]);
    // Excluded despite an overdue due_date: already Done/Cancelled.
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'due_date' => today()->subDay(),
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Cancelled,
        'due_date' => today()->subDay(),
    ]);

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}/overdue-tasks");

    // Assert
    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $overdue->id);
});

test('overdueManagedTasks_endedManagerRelation_excluded', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $project = Project::factory()->create();
    ProjectManager::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'end_date' => today()->subDay(),
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => today()->subDay(),
    ]);

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}/overdue-tasks");

    // Assert
    $response->assertSuccessful()->assertJsonCount(0, 'data');
});

test('overdueManagedTasks_employeeNotAManager_returnsEmpty', function () {
    // Arrange
    $employee = Employee::factory()->create();

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}/overdue-tasks");

    // Assert
    $response->assertSuccessful()->assertJsonCount(0, 'data');
});

test('overdueManagedTasks_selfService_employeeViewsOwn', function () {
    // Arrange
    $department = Department::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id]);
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::InProgress,
        'due_date' => today()->subDay(),
    ]);
    Sanctum::actingAs($employee, ['*']);

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}/overdue-tasks");

    // Assert
    $response->assertSuccessful()->assertJsonCount(1, 'data');
});

test('overdueManagedTasks_employeeViewsUnrelatedEmployee_forbidden', function () {
    // Arrange
    $viewer = Employee::factory()->create(['position' => 'employee']);
    $other = Employee::factory()->create(['position' => 'employee']);
    Sanctum::actingAs($viewer, ['*']);

    // Act
    $response = $this->getJson("/api/employees/{$other->id}/overdue-tasks");

    // Assert
    $response->assertForbidden();
});
