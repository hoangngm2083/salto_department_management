<?php

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Services\Approval\ApprovalStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('ensureCanApprove_submittedOrInReview_noException', function (ApprovalStatus $status) {
    // Arrange
    $approval = ApprovalRequest::factory()->make(['status' => $status]);
    $stateMachine = new ApprovalStateMachine;

    // Act & Assert
    expect(fn () => $stateMachine->ensureCanApprove($approval))->not->toThrow(ValidationException::class);
})->with([ApprovalStatus::Submitted, ApprovalStatus::InReview]);

test('ensureCanApprove_notAwaitingApprovalStatus_validationError', function (ApprovalStatus $status) {
    // Arrange
    $approval = ApprovalRequest::factory()->make(['status' => $status]);
    $stateMachine = new ApprovalStateMachine;

    // Act & Assert
    expect(fn () => $stateMachine->ensureCanApprove($approval))->toThrow(ValidationException::class);
})->with([
    ApprovalStatus::Draft,
    ApprovalStatus::Approved,
    ApprovalStatus::Rejected,
    ApprovalStatus::Cancelled,
    ApprovalStatus::Applied,
    ApprovalStatus::Failed,
]);

test('ensureCanReject_submittedOrInReview_noException', function (ApprovalStatus $status) {
    // Arrange
    $approval = ApprovalRequest::factory()->make(['status' => $status]);
    $stateMachine = new ApprovalStateMachine;

    // Act & Assert
    expect(fn () => $stateMachine->ensureCanReject($approval))->not->toThrow(ValidationException::class);
})->with([ApprovalStatus::Submitted, ApprovalStatus::InReview]);

test('ensureCanCancel_draftSubmittedOrInReview_noException', function (ApprovalStatus $status) {
    // Arrange
    $approval = ApprovalRequest::factory()->make(['status' => $status]);
    $stateMachine = new ApprovalStateMachine;

    // Act & Assert
    expect(fn () => $stateMachine->ensureCanCancel($approval))->not->toThrow(ValidationException::class);
})->with([ApprovalStatus::Draft, ApprovalStatus::Submitted, ApprovalStatus::InReview]);

test('ensureCanCancel_resolvedStatus_validationError', function (ApprovalStatus $status) {
    // Arrange
    $approval = ApprovalRequest::factory()->make(['status' => $status]);
    $stateMachine = new ApprovalStateMachine;

    // Act & Assert
    expect(fn () => $stateMachine->ensureCanCancel($approval))->toThrow(ValidationException::class);
})->with([
    ApprovalStatus::Approved,
    ApprovalStatus::Rejected,
    ApprovalStatus::Cancelled,
    ApprovalStatus::Applied,
    ApprovalStatus::Failed,
]);
