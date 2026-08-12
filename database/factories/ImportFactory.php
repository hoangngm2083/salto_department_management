<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\Import;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ImportType::Department,
            'status' => ImportStatus::Queued,
            'file_path' => 'imports/'.$this->faker->uuid().'.csv',
            'original_filename' => $this->faker->word().'.csv',
            'total' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'failed_count' => 0,
        ];
    }
}
