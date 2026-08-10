<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectManager;
use App\Models\Task;
use App\Models\TaskDelayRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('createDelayRequest_assigneeWithDueDate_created', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $employee->id, 'due_date' => today()->addDays(2)]);
    Sanctum::actingAs($employee, ['task-delay-requests:read', 'task-delay-requests:create']);

    // Act
    $response = $this->postJson('/api/task-delay-requests', [
        'task_id' => $task->id,
        'requested_due_date' => today()->addDays(5)->toDateString(),
        'reason' => 'Blocked by dependency',
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.task_id', $task->id)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.current_due_date', $task->due_date->toDateString());

    $this->assertDatabaseHas('task_delay_requests', ['task_id' => $task->id, 'status' => 'pending']);
});

test('createDelayRequest_taskWithNoDueDate_validationError', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $employee->id, 'due_date' => null]);
    Sanctum::actingAs($employee, ['task-delay-requests:read', 'task-delay-requests:create']);

    // Act
    $response = $this->postJson('/api/task-delay-requests', [
        'task_id' => $task->id,
        'requested_due_date' => today()->addDays(5)->toDateString(),
        'reason' => 'Blocked',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['task'], 'errors');
});

test('createDelayRequest_requestedDateNotAfterCurrentDueDate_validationError', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $employee->id, 'due_date' => today()->addDays(5)]);
    Sanctum::actingAs($employee, ['task-delay-requests:read', 'task-delay-requests:create']);

    // Act
    $response = $this->postJson('/api/task-delay-requests', [
        'task_id' => $task->id,
        'requested_due_date' => today()->addDays(5)->toDateString(),
        'reason' => 'Same date',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['requested_due_date'], 'errors');
});

test('createDelayRequest_alreadyPending_validationError', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $employee->id, 'due_date' => today()->addDays(5)]);
    TaskDelayRequest::factory()->create(['task_id' => $task->id, 'requested_by' => $employee->id, 'status' => 'pending']);
    Sanctum::actingAs($employee, ['task-delay-requests:read', 'task-delay-requests:create']);

    // Act
    $response = $this->postJson('/api/task-delay-requests', [
        'task_id' => $task->id,
        'requested_due_date' => today()->addDays(10)->toDateString(),
        'reason' => 'Another one',
    ]);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['task'], 'errors');
});

test('createDelayRequest_notAssignee_forbidden', function () {
    // Arrange
    $project = Project::factory()->create();
    $assignee = Employee::factory()->create(['status' => 'active']);
    $otherEmployee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $assignee->id, 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $assignee->id, 'due_date' => today()->addDays(5)]);
    Sanctum::actingAs($otherEmployee, ['task-delay-requests:read', 'task-delay-requests:create']);

    // Act
    $response = $this->postJson('/api/task-delay-requests', [
        'task_id' => $task->id,
        'requested_due_date' => today()->addDays(10)->toDateString(),
        'reason' => 'Blocked',
    ]);

    // Assert
    $response->assertForbidden();
});

test('updateDelayRequest_projectManagerApproves_taskDueDateUpdated', function () {
    // Arrange
    $department = Department::factory()->create();
    $pm = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id, 'status' => 'active']);
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id, 'end_date' => null]);
    $task = Task::factory()->create(['project_id' => $project->id, 'due_date' => today()->addDays(2)]);
    $requestedDueDate = today()->addDays(9)->toDateString();
    $delayRequest = TaskDelayRequest::factory()->create([
        'task_id' => $task->id,
        'current_due_date' => $task->due_date,
        'requested_due_date' => $requestedDueDate,
        'status' => 'pending',
    ]);
    Sanctum::actingAs($pm, ['task-delay-requests:read', 'task-delay-requests:update']);

    // Act
    $response = $this->patchJson("/api/task-delay-requests/{$delayRequest->id}", ['status' => 'approved']);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.reviewed_by', $pm->id);

    expect($task->fresh()->due_date->toDateString())->toBe($requestedDueDate);
});

test('updateDelayRequest_requesterCancelsOwnPending_cancelled', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $employee->id, 'due_date' => today()->addDays(2)]);
    $delayRequest = TaskDelayRequest::factory()->create(['task_id' => $task->id, 'requested_by' => $employee->id, 'status' => 'pending']);
    Sanctum::actingAs($employee, ['task-delay-requests:read', 'task-delay-requests:update']);

    // Act
    $response = $this->patchJson("/api/task-delay-requests/{$delayRequest->id}", ['status' => 'cancelled']);

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.status', 'cancelled');
});

test('updateDelayRequest_notProjectManagerNorRequester_forbidden', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);
    $otherEmployee = Employee::factory()->create(['status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $employee->id, 'due_date' => today()->addDays(2)]);
    $delayRequest = TaskDelayRequest::factory()->create(['task_id' => $task->id, 'requested_by' => $employee->id, 'status' => 'pending']);
    Sanctum::actingAs($otherEmployee, ['task-delay-requests:read', 'task-delay-requests:update']);

    // Act
    $response = $this->patchJson("/api/task-delay-requests/{$delayRequest->id}", ['status' => 'approved']);

    // Assert
    $response->assertForbidden();
});

test('getDelayRequests_taskIdFilter_matchingReturned', function () {
    // Arrange
    $project = Project::factory()->create();
    $task = Task::factory()->create(['project_id' => $project->id]);
    TaskDelayRequest::factory()->count(2)->create(['task_id' => $task->id]);
    TaskDelayRequest::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'status' => 'active']);
    Sanctum::actingAs($manager, ['task-delay-requests:read']);

    // Act
    $response = $this->getJson("/api/task-delay-requests?task_id={$task->id}");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(2);
});

test('getDelayRequests_employeeActor_scopedToOwnRequests', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee', 'status' => 'active']);
    $otherEmployee = Employee::factory()->create(['status' => 'active']);
    $task = Task::factory()->create();
    TaskDelayRequest::factory()->create(['task_id' => $task->id, 'requested_by' => $employee->id]);
    TaskDelayRequest::factory()->create(['task_id' => $task->id, 'requested_by' => $otherEmployee->id]);
    Sanctum::actingAs($employee, ['task-delay-requests:read']);

    // Act
    $response = $this->getJson('/api/task-delay-requests');

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(1);
    expect($response->json('data.data.0.requested_by'))->toBe($employee->id);
});
