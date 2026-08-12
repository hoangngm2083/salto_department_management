<?php

use App\Enums\WorkflowType;
use App\Models\Employee;
use App\Models\RoleChangeRequest;
use App\Services\Approval\Contracts\ApprovableRequest;
use App\Services\Approval\Contracts\ApprovalWorkflow;
use App\Services\Approval\EmptyApprovalWorkflow;
use App\Services\ApprovalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('submit_workflowResolvesZeroSteps_throwsAndDoesNotPersistRequest', function () {
    // Arrange - a misconfigured future workflow that never defines any approval step.
    $workflow = new class implements ApprovalWorkflow
    {
        public function type(): WorkflowType
        {
            return WorkflowType::ProjectRoleChange;
        }

        public function steps(ApprovableRequest $request): array
        {
            return [];
        }
    };
    $actor = Employee::factory()->create();
    $requestable = RoleChangeRequest::factory()->create(['created_by' => $actor->id]);

    // Act & Assert
    expect(fn () => app(ApprovalRequestService::class)->submit($actor, $actor, $requestable, $workflow))
        ->toThrow(EmptyApprovalWorkflow::class, 'Approval workflow [project_role_change] resolved zero steps for this request.');

    $this->assertDatabaseCount('approval_requests', 0);
});
