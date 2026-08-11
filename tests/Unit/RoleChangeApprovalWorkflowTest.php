<?php

use App\Enums\ApproverKind;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectManager;
use App\Models\RoleChangeRequest;
use App\Services\Approval\Workflows\RoleChangeApprovalWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('steps_singlePmNotRequester_resolvesProjectManagerStep', function () {
    // Arrange
    $project = Project::factory()->create();
    $pm = Employee::factory()->create(['position' => 'manager']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id]);
    $subject = Employee::factory()->create();
    $assignment = ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $subject->id]);
    $roleChangeRequest = RoleChangeRequest::factory()->create([
        'project_assignment_id' => $assignment->id,
        'created_by' => $subject->id,
    ]);
    $workflow = new RoleChangeApprovalWorkflow;

    // Act
    $steps = $workflow->steps($roleChangeRequest);

    // Assert
    expect($steps)->toHaveCount(1);
    expect($steps[0]->approverKind)->toBe(ApproverKind::ProjectManager);
    expect($steps[0]->approverEmployeeId)->toBe($pm->id);
});

test('steps_multiplePmsRequesterIsOneOfThem_excludesRequester', function () {
    // Arrange
    $project = Project::factory()->create();
    $requesterPm = Employee::factory()->create(['position' => 'manager']);
    $otherPm = Employee::factory()->create(['position' => 'manager']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $requesterPm->id, 'start_date' => today()->subDays(10)]);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $otherPm->id, 'start_date' => today()->subDays(5)]);
    $subject = Employee::factory()->create();
    $assignment = ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $subject->id]);
    $roleChangeRequest = RoleChangeRequest::factory()->create([
        'project_assignment_id' => $assignment->id,
        'created_by' => $requesterPm->id,
    ]);
    $workflow = new RoleChangeApprovalWorkflow;

    // Act
    $steps = $workflow->steps($roleChangeRequest);

    // Assert - the earlier-appointed PM would normally be picked first, but they're the
    // requester, so the other active PM is resolved instead.
    expect($steps)->toHaveCount(1);
    expect($steps[0]->approverKind)->toBe(ApproverKind::ProjectManager);
    expect($steps[0]->approverEmployeeId)->toBe($otherPm->id);
});

test('steps_soleActivePmIsRequester_fallsBackToSystemAdmin', function () {
    // Arrange
    $project = Project::factory()->create();
    $pm = Employee::factory()->create(['position' => 'manager']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id]);
    $subject = Employee::factory()->create();
    $assignment = ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $subject->id]);
    $roleChangeRequest = RoleChangeRequest::factory()->create([
        'project_assignment_id' => $assignment->id,
        'created_by' => $pm->id,
    ]);
    $workflow = new RoleChangeApprovalWorkflow;

    // Act
    $steps = $workflow->steps($roleChangeRequest);

    // Assert - nobody else could legally act on a ProjectManager step here, so it routes to
    // SystemAdmin instead of producing a step only its own author could resolve.
    expect($steps)->toHaveCount(1);
    expect($steps[0]->approverKind)->toBe(ApproverKind::SystemAdmin);
    expect($steps[0]->approverEmployeeId)->toBeNull();
});
