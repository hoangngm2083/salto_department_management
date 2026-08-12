<?php

namespace App\Events;

use App\Models\ApprovalRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a request reaches a terminal outcome the subject employee should hear about:
 * Applied (approved and the business change went through), Failed (approved but the change
 * couldn't be applied - e.g. stale data), or Rejected. Not fired on Cancelled - the requester
 * already knows, they did it themselves. Generic to the Approval Engine, not workflow-specific.
 */
class ApprovalRequestDecided
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ApprovalRequest $approval) {}
}
