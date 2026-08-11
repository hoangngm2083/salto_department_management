<?php

namespace Database\Factories;

use App\Enums\ApprovalActionType;
use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalAction>
 */
class ApprovalActionFactory extends Factory
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
            'approval_step_id' => ApprovalStep::factory(),
            'actor_employee_id' => Employee::factory(),
            'action_type' => ApprovalActionType::Approve,
        ];
    }
}
