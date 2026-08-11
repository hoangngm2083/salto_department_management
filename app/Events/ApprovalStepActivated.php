<?php

namespace App\Events;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a step becomes the active one - at submit for step 1, and again on
 * approve() whenever another step follows. Generic to the Approval Engine itself (not
 * Role-Change-specific) so every future workflow (F/G) gets "notify the next approver" for
 * free just by tagging into the engine, per new_business.md §28's Observer-pattern intent.
 */
class ApprovalStepActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ApprovalRequest $approval,
        public readonly ApprovalStep $step,
    ) {}
}
