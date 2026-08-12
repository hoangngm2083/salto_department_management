<?php

namespace Database\Factories;

use App\Enums\JobApplicationStatus;
use App\Models\Applicant;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'job_posting_id' => JobPosting::factory(),
            'channel' => 'company_career_page',
            'cover_letter' => $this->faker->optional()->paragraph(),
            'resume_path' => 'resumes/'.date('Y/m/d').'/'.Str::random(20).'.pdf',
            'status' => JobApplicationStatus::Submitted,
            'talent_pool' => false,
        ];
    }
}
