<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\ProjectRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectRole>
 */
class ProjectRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Frontend',
            'Backend',
            'DevOps',
            'QA',
            'QC',
            'BrSE',
            'BA',
            'Tech Lead',
            'Project Manager',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => null,
            'status' => ActiveStatus::Active,
        ];
    }
}
