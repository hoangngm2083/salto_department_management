<?php

namespace App\Policies;

use App\Enums\ApprovalStepStatus;
use App\Enums\ApproverKind;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;

class ApprovalRequestPolicy
{
    public function before(Employee $employee, string $ability): ?bool
    {
        return $employee->position === 'admin' ? true : null;
    }

    public function viewAny(Employee $employee): bool
    {
        return true;
    }

    /**
     * Visible to the requester, the request's subject employee, the resolved approver of
     * the currently active step, and anyone who already acted on a prior step.
     */
    public function view(Employee $employee, ApprovalRequest $approval): bool
    {
        if ($employee->id === $approval->requested_by || $employee->id === $approval->subject_employee_id) {
            return true;
        }

        if ($approval->steps->contains('acted_by', $employee->id)) {
            return true;
        }

        return $this->canActOnStep($employee, $approval, $approval->activeStep);
    }

    /**
     * $type is one of 'approve' | 'reject' | 'cancel' (validated by UpdateApprovalRequest)
     * and gates the single PATCH .../{approval} endpoint.
     */
    public function update(Employee $employee, ApprovalRequest $approval, string $type): bool
    {
        if ($type === 'cancel') {
            return $employee->id === $approval->requested_by;
        }

        // Requester may not approve/reject their own request.
        if ($employee->id === $approval->requested_by) {
            return false;
        }

        return $this->canActOnStep($employee, $approval, $approval->activeStep);
    }

    /**
     * Whether the actor is the resolved approver of the given step, based on its
     * ApproverKind. Steps with a pre-resolved approver_employee_id (DirectManager/
     * ProjectManager/SpecificEmployee) compare directly; pool kinds are resolved
     * generically here so the engine never has to reach into Project/Department
     * internals itself.
     */
    private function canActOnStep(Employee $employee, ApprovalRequest $approval, ?ApprovalStep $step): bool
    {
        if ($step === null || $step->status !== ApprovalStepStatus::Active) {
            return false;
        }

        if ($step->approver_employee_id !== null) {
            return $employee->id === $step->approver_employee_id;
        }

        // ApproverKind::SystemAdmin isn't matched here: an actual admin already bypasses
        // this whole policy via before(), so a SystemAdmin-kind step can never legitimately
        // reach this code path - and it must never resolve true for a non-admin.
        return match ($step->approver_kind) {
            ApproverKind::DepartmentManager => $employee->position === 'manager'
                && $employee->department_id === $approval->subjectEmployee?->department_id,
            ApproverKind::Permission => $step->required_permission !== null && $employee->tokenCan($step->required_permission),
            default => false,
        };
    }
}
