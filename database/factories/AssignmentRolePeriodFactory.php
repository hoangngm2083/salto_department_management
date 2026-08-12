<?php

namespace Database\Factories;

use App\Models\AssignmentRolePeriod;
use App\Models\ProjectAssignment;
use App\Models\ProjectRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentRolePeriod>
 */
class AssignmentRolePeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_assignment_id' => ProjectAssignment::factory(),
            'project_role_id' => ProjectRole::factory(),
            'start_date' => today(),
            'end_date' => null,
            'source_approval_request_id' => null,
        ];
    }
}
