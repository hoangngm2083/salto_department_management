<?php

use App\Enums\WorkflowType;
use App\Models\ApprovalRequest;
use App\Services\Approval\ApprovedRequestHandlerRegistry;
use App\Services\Approval\Contracts\ApprovedRequestHandler;
use Tests\TestCase;

uses(TestCase::class);

test('get_registeredType_returnsMatchingHandler', function () {
    // Arrange
    $handler = new class implements ApprovedRequestHandler
    {
        public function type(): WorkflowType
        {
            return WorkflowType::LevelPromotion;
        }

        public function apply(ApprovalRequest $approval): void {}
    };
    $registry = new ApprovedRequestHandlerRegistry([$handler]);

    // Act & Assert
    expect($registry->get(WorkflowType::LevelPromotion))->toBe($handler);
});

test('get_unregisteredType_throws', function () {
    // Arrange
    $registry = new ApprovedRequestHandlerRegistry([]);

    // Act & Assert
    expect(fn () => $registry->get(WorkflowType::ProjectTransfer))
        ->toThrow(RuntimeException::class, 'No approval workflow registered for type [project_transfer].');
});
