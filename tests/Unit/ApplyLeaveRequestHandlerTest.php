<?php

use App\Models\ApprovalRequest;
use App\Models\LeaveRequest;
use App\Services\Approval\Handlers\ApplyLeaveRequestHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('apply_fullyApprovedLeaveRequest_runsWithoutErrorAndMutatesNothing', function () {
    // Arrange
    $leaveRequest = LeaveRequest::factory()->create();
    $approval = ApprovalRequest::factory()->create([
        'requestable_type' => LeaveRequest::class,
        'requestable_id' => $leaveRequest->id,
    ]);
    $handler = app(ApplyLeaveRequestHandler::class);
    $leaveRequestBefore = $leaveRequest->fresh()->getAttributes();

    // Act
    $handler->apply($approval);

    // Assert - a fully-approved leave request has no downstream state to mutate
    expect($leaveRequest->fresh()->getAttributes())->toBe($leaveRequestBefore);
});
