<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Human Resources',
            'Finance & Accounting',
            'Information Technology',
            'Marketing & Sales',
            'Customer Support',
            'Research & Development',
            'Legal & Compliance',
            'Operations',
        ]);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(10),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }
}
