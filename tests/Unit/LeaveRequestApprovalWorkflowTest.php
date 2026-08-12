<?php

use App\Enums\ApproverKind;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\ProjectManager;
use App\Services\Approval\Workflows\LeaveRequestApprovalWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('steps_projectPickedWithActivePm_resolvesPmThenDirectManagerSteps', function () {
    // Arrange
    $hrManager = Employee::factory()->create(['position' => 'manager']);
    $employee = Employee::factory()->create(['manager_employee_id' => $hrManager->id]);
    $project = Project::factory()->create();
    $pm = Employee::factory()->create(['position' => 'manager']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'project_id' => $project->id]);
    $workflow = new LeaveRequestApprovalWorkflow;

    // Act
    $steps = $workflow->steps($leaveRequest);

    // Assert
    expect($steps)->toHaveCount(2);
    expect($steps[0]->approverKind)->toBe(ApproverKind::ProjectManager);
    expect($steps[0]->approverEmployeeId)->toBe($pm->id);
    expect($steps[1]->approverKind)->toBe(ApproverKind::DirectManager);
    expect($steps[1]->approverEmployeeId)->toBe($hrManager->id);
});

test('steps_noProjectPicked_resolvesDirectManagerStepOnly', function () {
    // Arrange
    $hrManager = Employee::factory()->create(['position' => 'manager']);
    $employee = Employee::factory()->create(['manager_employee_id' => $hrManager->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'project_id' => null]);
    $workflow = new LeaveRequestApprovalWorkflow;

    // Act
    $steps = $workflow->steps($leaveRequest);

    // Assert
    expect($steps)->toHaveCount(1);
    expect($steps[0]->approverKind)->toBe(ApproverKind::DirectManager);
    expect($steps[0]->approverEmployeeId)->toBe($hrManager->id);
});

test('steps_soleActivePmIsTheEmployee_pmStepOmittedNotFallenBackToAdmin', function () {
    // Arrange - unlike RoleChangeApprovalWorkflow, this must NOT fall back to SystemAdmin,
    // since the DirectManager (HR) step is still a mandatory second gate either way.
    $hrManager = Employee::factory()->create(['position' => 'manager']);
    $employee = Employee::factory()->create(['position' => 'manager', 'manager_employee_id' => $hrManager->id]);
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'project_id' => $project->id]);
    $workflow = new LeaveRequestApprovalWorkflow;

    // Act
    $steps = $workflow->steps($leaveRequest);

    // Assert
    expect($steps)->toHaveCount(1);
    expect($steps[0]->approverKind)->toBe(ApproverKind::DirectManager);
    expect($steps[0]->approverEmployeeId)->toBe($hrManager->id);
});

test('steps_employeeHasNoManager_directManagerStepFallsBackToSystemAdmin', function () {
    // Arrange
    $employee = Employee::factory()->create(['manager_employee_id' => null]);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'project_id' => null]);
    $workflow = new LeaveRequestApprovalWorkflow;

    // Act
    $steps = $workflow->steps($leaveRequest);

    // Assert
    expect($steps)->toHaveCount(1);
    expect($steps[0]->approverKind)->toBe(ApproverKind::SystemAdmin);
    expect($steps[0]->approverEmployeeId)->toBeNull();
});
