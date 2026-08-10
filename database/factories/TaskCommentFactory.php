<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskComment>
 */
class TaskCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'employee_id' => Employee::factory(),
            'body' => $this->faker->sentence(8),
            'task_status' => null,
        ];
    }
}
