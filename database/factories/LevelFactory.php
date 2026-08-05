<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Level>
 */
class LevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $levels = [
            ['name' => 'Intern', 'rank' => 10],
            ['name' => 'Fresher', 'rank' => 20],
            ['name' => 'Probation', 'rank' => 30],
            ['name' => 'Junior', 'rank' => 40],
            ['name' => 'Middle', 'rank' => 50],
            ['name' => 'Senior', 'rank' => 60],
            ['name' => 'Principal', 'rank' => 70],
        ];

        ['name' => $name, 'rank' => $rank] = $this->faker->unique()->randomElement($levels);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'rank' => $rank,
            'probation_salary_percentage' => null,
            'status' => ActiveStatus::Active,
        ];
    }
}
