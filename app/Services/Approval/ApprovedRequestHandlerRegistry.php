<?php

namespace App\Services\Approval;

use App\Enums\WorkflowType;
use App\Services\Approval\Contracts\ApprovedRequestHandler;

final class ApprovedRequestHandlerRegistry
{
    /**
     * @param  iterable<ApprovedRequestHandler>  $handlers
     */
    public function __construct(private readonly iterable $handlers) {}

    public function get(WorkflowType $type): ApprovedRequestHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->type() === $type) {
                return $handler;
            }
        }

        throw new UnsupportedWorkflowType($type);
    }
}
