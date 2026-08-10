<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'assigned_to' => null,
            'created_by' => Employee::factory(),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'status' => TaskStatus::Todo,
            'due_date' => $this->faker->optional()->dateTimeBetween('now', '+1 month')?->format('Y-m-d'),
        ];
    }
}
