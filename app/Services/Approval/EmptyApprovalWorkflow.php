<?php

namespace App\Services\Approval;

use App\Enums\WorkflowType;
use RuntimeException;

/**
 * Thrown when an ApprovalWorkflow::steps() implementation returns no steps at all - a
 * configuration error (every workflow must define at least one approval step), not a
 * user-facing validation failure. Without this guard, submit() would silently create an
 * ApprovalRequest stuck in Submitted with no active step and no way to progress.
 */
final class EmptyApprovalWorkflow extends RuntimeException
{
    public function __construct(WorkflowType $type)
    {
        parent::__construct("Approval workflow [{$type->value}] resolved zero steps for this request.");
    }
}
