<?php

namespace Database\Factories;

use App\Enums\ProjectAssignmentStatus;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectAssignment>
 */
class ProjectAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'employee_id' => Employee::factory(),
            'start_date' => today(),
            'end_date' => null,
            'status' => ProjectAssignmentStatus::Active,
            'assigned_by' => Employee::factory(),
        ];
    }
}
