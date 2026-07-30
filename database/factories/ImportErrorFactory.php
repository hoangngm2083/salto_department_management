<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\ImportError;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportError>
 */
class ImportErrorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_id' => Import::factory(),
            'row' => $this->faker->numberBetween(2, 1000),
            'message' => $this->faker->sentence(),
            'payload' => ['name' => $this->faker->word()],
        ];
    }
}
