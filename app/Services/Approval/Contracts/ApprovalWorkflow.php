<?php

namespace App\Services\Approval\Contracts;

use App\Enums\WorkflowType;
use App\Services\Approval\ApprovalStepDefinition;

interface ApprovalWorkflow
{
    public function type(): WorkflowType;

    /**
     * Build the ordered list of approval steps for this specific request instance.
     * Steps may vary per request - e.g. a direct-manager step can be omitted entirely
     * when the subject employee has no manager_employee_id set.
     *
     * @return list<ApprovalStepDefinition>
     */
    public function steps(ApprovableRequest $request): array;
}
