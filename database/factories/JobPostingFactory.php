<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\JobPostingStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst($this->faker->unique()->jobTitle());

        return [
            'title' => $title,
            'slug' => Str::slug($title.'-'.Str::random(6)),
            'department_id' => Department::factory(),
            'project_id' => null,
            'description' => $this->faker->paragraph(),
            'requirements' => $this->faker->paragraph(),
            'employment_type' => EmploymentType::FullTime,
            'slots_needed' => $this->faker->numberBetween(1, 5),
            'status' => JobPostingStatus::Draft,
            'created_by' => Employee::factory(),
            'published_at' => null,
            'closed_at' => null,
        ];
    }
}
