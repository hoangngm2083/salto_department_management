<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
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

    // Persisted (not ->make()) because task creation/review writes the
    // actor's id into tasks.created_by / reviewed_by, both NOT NULL/FK columns.
    Sanctum::actingAs(
        Employee::factory()->create([
            'position' => 'admin',
            'department_id' => $adminDepartment->id,
        ]),
        ['*']
    );
});

test('createTask_validPayload_created', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'status' => 'active']);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/tasks", [
        'title' => 'Implement login',
        'description' => 'Add login form',
        'due_date' => today()->addDays(5)->toDateString(),
        'assigned_to' => $employee->id,
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Implement login')
        ->assertJsonPath('data.status', 'todo')
        ->assertJsonPath('data.assigned_to', $employee->id);

    $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => 'Implement login', 'status' => 'todo']);
});

test('createTask_assignedToEmployeeWithoutActiveAssignment_validationError', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/tasks", [
        'title' => 'Implement login',
        'assigned_to' => $employee->id,
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['assigned_to'], 'errors');
});

test('createTask_missingTitle_validationError', function () {
    // Arrange
    $project = Project::factory()->create();

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/tasks", []);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['title'], 'errors');
});

test('createTask_asProjectManager_created', function () {
    // Arrange
    $department = Department::factory()->create();
    $pm = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id, 'status' => 'active']);
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id, 'end_date' => null]);
    Sanctum::actingAs($pm, ['tasks:read', 'tasks:create']);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/tasks", ['title' => 'PM created task']);

    // Assert
    $response->assertCreated();
});

test('createTask_managerNotProjectManager_forbidden', function () {
    // Arrange
    $manager = Employee::factory()->create(['position' => 'manager', 'status' => 'active']);
    $project = Project::factory()->create();
    Sanctum::actingAs($manager, ['tasks:read', 'tasks:create']);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/tasks", ['title' => 'Task']);

    // Assert
    $response->assertForbidden();
});

test('getTasks_listsForProject', function () {
    // Arrange
    $project = Project::factory()->create();
    Task::factory()->count(2)->create(['project_id' => $project->id]);
    Task::factory()->create();

    // Act
    $response = $this->getJson("/api/projects/{$project->slug}/tasks");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(2);
});

test('getTasks_statusFilter_matchingTasksReturned', function () {
    // Arrange
    $project = Project::factory()->create();
    Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);
    Task::factory()->create(['project_id' => $project->id, 'status' => 'done']);

    // Act
    $response = $this->getJson("/api/projects/{$project->slug}/tasks?status=done");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(1);
    expect($response->json('data.data.0.status'))->toBe('done');
});

test('getTask_show_returnsTask', function () {
    // Arrange
    $task = Task::factory()->create(['title' => 'A task']);

    // Act
    $response = $this->getJson("/api/tasks/{$task->id}");

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.title', 'A task');
});

test('myTasks_listsAcrossProjects', function () {
    // Arrange
    $employee = Employee::factory()->create(['status' => 'active']);
    Task::factory()->count(2)->create(['assigned_to' => $employee->id]);
    Task::factory()->create();

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}/tasks");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(2);
});

test('updateTaskStatus_assigneeTodoToInProgress_updated', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $employee->id, 'status' => 'todo']);
    Sanctum::actingAs($employee, ['tasks:read', 'tasks:update']);

    // Act
    $response = $this->patchJson("/api/tasks/{$task->id}", ['status' => 'in_progress']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'in_progress');
});

test('updateTaskStatus_managerApprovesFromInReview_reviewedFieldsSet', function () {
    // Arrange
    $department = Department::factory()->create();
    $pm = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id, 'status' => 'active']);
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id, 'end_date' => null]);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'in_review']);
    Sanctum::actingAs($pm, ['tasks:read', 'tasks:update']);

    // Act
    $response = $this->patchJson("/api/tasks/{$task->id}", ['status' => 'done']);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.reviewed_by', $pm->id);
    expect($task->fresh()->reviewed_at)->not->toBeNull();
});

test('updateTaskStatus_managerRejectsFromInReview_backToInProgressWithNote', function () {
    // Arrange
    $department = Department::factory()->create();
    $pm = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id, 'status' => 'active']);
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id, 'end_date' => null]);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'in_review']);
    Sanctum::actingAs($pm, ['tasks:read', 'tasks:update']);

    // Act
    $response = $this->patchJson("/api/tasks/{$task->id}", ['status' => 'in_progress', 'review_note' => 'Missing tests']);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.review_note', 'Missing tests');
});

test('updateTaskStatus_cancelFromTodo_asProjectManager_cancelled', function () {
    // Arrange
    $department = Department::factory()->create();
    $pm = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id, 'status' => 'active']);
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id, 'end_date' => null]);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);
    Sanctum::actingAs($pm, ['tasks:read', 'tasks:update']);

    // Act
    $response = $this->patchJson("/api/tasks/{$task->id}", ['status' => 'cancelled']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'cancelled');
});

test('updateTaskStatus_cancelFromDone_forbidden', function () {
    // Arrange
    $department = Department::factory()->create();
    $pm = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id, 'status' => 'active']);
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id, 'end_date' => null]);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'done']);
    Sanctum::actingAs($pm, ['tasks:read', 'tasks:update']);

    // Act
    $response = $this->patchJson("/api/tasks/{$task->id}", ['status' => 'cancelled']);

    // Assert
    $response->assertForbidden();
});
