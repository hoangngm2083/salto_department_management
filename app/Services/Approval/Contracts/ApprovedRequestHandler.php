<?php

namespace App\Services\Approval\Contracts;

use App\Enums\WorkflowType;
use App\Models\ApprovalRequest;

interface ApprovedRequestHandler
{
    public function type(): WorkflowType;

    /**
     * Apply the business change for a fully-approved request. Should throw on failure
     * (e.g. stale underlying data) rather than silently no-op - the caller catches this
     * and records the request as Failed instead of Applied.
     */
    public function apply(ApprovalRequest $approval): void;
}
