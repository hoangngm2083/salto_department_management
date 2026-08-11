<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\WorkflowType;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * No concrete business request model exists yet in Phase D, so an Employee row - an
     * existing, always-available model - stands in as the polymorphic `requestable`
     * target purely to give the morph columns a valid FK-shaped value in tests.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requestable_type' => Employee::class,
            'requestable_id' => Employee::factory(),
            'workflow_type' => WorkflowType::ProjectRoleChange,
            'requested_by' => Employee::factory(),
            'subject_employee_id' => Employee::factory(),
            'status' => ApprovalStatus::Submitted,
            'current_step_order' => 1,
            'submitted_at' => now(),
        ];
    }
}
