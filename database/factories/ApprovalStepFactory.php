<?php

namespace Database\Factories;

use App\Enums\ApprovalStepStatus;
use App\Enums\ApproverKind;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalStep>
 */
class ApprovalStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_request_id' => ApprovalRequest::factory(),
            'step_order' => 1,
            'approver_kind' => ApproverKind::SpecificEmployee,
            'approver_employee_id' => Employee::factory(),
            'status' => ApprovalStepStatus::Active,
        ];
    }
}
