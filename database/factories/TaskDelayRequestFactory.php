<?php

namespace Database\Factories;

use App\Enums\TaskDelayRequestStatus;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskDelayRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskDelayRequest>
 */
class TaskDelayRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currentDueDate = $this->faker->dateTimeBetween('now', '+1 month');
        $requestedDueDate = (clone $currentDueDate)->modify('+'.$this->faker->numberBetween(1, 10).' days');

        return [
            'task_id' => Task::factory(),
            'requested_by' => Employee::factory(),
            'current_due_date' => $currentDueDate->format('Y-m-d'),
            'requested_due_date' => $requestedDueDate->format('Y-m-d'),
            'reason' => $this->faker->sentence(10),
            'status' => TaskDelayRequestStatus::Pending,
        ];
    }
}
