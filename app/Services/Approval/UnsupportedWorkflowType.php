<?php

namespace App\Services\Approval;

use App\Enums\WorkflowType;
use RuntimeException;

/**
 * Thrown when a registry has no ApprovalWorkflow/ApprovedRequestHandler bound for a given
 * type - a configuration error (a phase forgot to tag its implementation), not a
 * user-facing validation failure.
 */
final class UnsupportedWorkflowType extends RuntimeException
{
    public function __construct(WorkflowType $type)
    {
        parent::__construct("No approval workflow registered for type [{$type->value}].");
    }
}
