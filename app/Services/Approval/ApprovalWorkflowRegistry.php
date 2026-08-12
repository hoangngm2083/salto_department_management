<?php

namespace App\Services\Approval;

use App\Enums\WorkflowType;
use App\Services\Approval\Contracts\ApprovalWorkflow;

final class ApprovalWorkflowRegistry
{
    /**
     * @param  iterable<ApprovalWorkflow>  $workflows
     */
    public function __construct(private readonly iterable $workflows) {}

    public function get(WorkflowType $type): ApprovalWorkflow
    {
        foreach ($this->workflows as $workflow) {
            if ($workflow->type() === $type) {
                return $workflow;
            }
        }

        throw new UnsupportedWorkflowType($type);
    }
}
