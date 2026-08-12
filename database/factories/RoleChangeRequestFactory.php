<?php

namespace Database\Factories;

use App\Enums\RoleChangeMode;
use App\Models\Employee;
use App\Models\ProjectAssignment;
use App\Models\ProjectRole;
use App\Models\RoleChangeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleChangeRequest>
 */
class RoleChangeRequestFactory extends Factory
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
            'change_mode' => RoleChangeMode::Add,
            'from_project_role_id' => null,
            'to_project_role_id' => ProjectRole::factory(),
            'reason' => $this->faker->sentence(10),
            'created_by' => Employee::factory(),
        ];
    }
}
