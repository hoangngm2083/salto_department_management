<?php

namespace App\Services\Approval;

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use Illuminate\Validation\ValidationException;

/**
 * Pure lifecycle-transition guards - no DB side effects. Business-rule violations are
 * reported as ValidationException, matching the convention already used throughout the
 * app's Services (e.g. TaskDelayRequestService, ProjectAssignmentService) rather than a
 * bespoke exception hierarchy.
 */
final class ApprovalStateMachine
{
    /**
     * @var list<ApprovalStatus>
     */
    private const ACTIONABLE_STATUSES = [ApprovalStatus::Submitted, ApprovalStatus::InReview];

    /**
     * @var list<ApprovalStatus>
     */
    private const CANCELLABLE_STATUSES = [ApprovalStatus::Draft, ApprovalStatus::Submitted, ApprovalStatus::InReview];

    public function ensureCanApprove(ApprovalRequest $approval): void
    {
        $this->ensureActionable($approval);
    }

    public function ensureCanReject(ApprovalRequest $approval): void
    {
        $this->ensureActionable($approval);
    }

    public function ensureCanCancel(ApprovalRequest $approval): void
    {
        if (! in_array($approval->status, self::CANCELLABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'approval' => ['This request can no longer be cancelled.'],
            ]);
        }
    }

    private function ensureActionable(ApprovalRequest $approval): void
    {
        if (! in_array($approval->status, self::ACTIONABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'approval' => ['This request is not awaiting approval.'],
            ]);
        }
    }
}
