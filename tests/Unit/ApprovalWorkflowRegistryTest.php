<?php

use App\Enums\WorkflowType;
use App\Services\Approval\ApprovalWorkflowRegistry;
use App\Services\Approval\Contracts\ApprovableRequest;
use App\Services\Approval\Contracts\ApprovalWorkflow;
use Tests\TestCase;

uses(TestCase::class);

test('get_registeredType_returnsMatchingWorkflow', function () {
    // Arrange
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
    $registry = new ApprovalWorkflowRegistry([$workflow]);

    // Act & Assert
    expect($registry->get(WorkflowType::ProjectRoleChange))->toBe($workflow);
});

test('get_unregisteredType_throws', function () {
    // Arrange
    $registry = new ApprovalWorkflowRegistry([]);

    // Act & Assert
    expect(fn () => $registry->get(WorkflowType::LevelPromotion))
        ->toThrow(RuntimeException::class, 'No approval workflow registered for type [level_promotion].');
});
