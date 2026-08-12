<?php

namespace Database\Factories;

use App\Enums\JobPostingChannelStatus;
use App\Models\JobPosting;
use App\Models\JobPostingChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPostingChannel>
 */
class JobPostingChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'channel' => 'company_career_page',
            'status' => JobPostingChannelStatus::Pending,
            'external_ref' => null,
            'dispatched_at' => null,
            'error_message' => null,
        ];
    }
}
