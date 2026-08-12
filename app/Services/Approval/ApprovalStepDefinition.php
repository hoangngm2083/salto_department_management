<?php

namespace App\Services\Approval;

use App\Enums\ApproverKind;

/**
 * Value object returned by ApprovalWorkflow::steps(). Kinds that resolve to exactly one
 * known employee at step-creation time (DirectManager/ProjectManager/SpecificEmployee)
 * require the caller to resolve and pass that employee's id - e.g. via
 * Project::activeManagers() for a PM step - so the generic engine never has to reach into
 * Project/Department internals itself. Pool kinds (SystemAdmin/DepartmentManager/
 * Permission) resolve dynamically at approve-time instead, see ApprovalRequestPolicy.
 */
final readonly class ApprovalStepDefinition
{
    private function __construct(
        public ApproverKind $approverKind,
        public ?int $approverEmployeeId = null,
        public ?string $requiredPermission = null,
    ) {}

    public static function directManager(int $approverEmployeeId): self
    {
        return new self(ApproverKind::DirectManager, approverEmployeeId: $approverEmployeeId);
    }

    public static function projectManager(int $approverEmployeeId): self
    {
        return new self(ApproverKind::ProjectManager, approverEmployeeId: $approverEmployeeId);
    }

    public static function specificEmployee(int $approverEmployeeId): self
    {
        return new self(ApproverKind::SpecificEmployee, approverEmployeeId: $approverEmployeeId);
    }

    public static function departmentManager(): self
    {
        return new self(ApproverKind::DepartmentManager);
    }

    public static function systemAdmin(): self
    {
        return new self(ApproverKind::SystemAdmin);
    }

    public static function permission(string $permission): self
    {
        return new self(ApproverKind::Permission, requiredPermission: $permission);
    }
}
