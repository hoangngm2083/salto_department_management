<?php

use App\Enums\JobPostingChannelStatus;
use App\Models\JobPosting;
use App\Models\JobPostingChannel;
use App\Services\Recruitment\JobPostingDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('dispatch_activeChannelConfigured_logsDispatchedChannelRow', function () {
    // Arrange
    $jobPosting = JobPosting::factory()->create();
    $dispatcher = new JobPostingDispatcher;

    // Act
    $dispatcher->dispatch($jobPosting);

    // Assert
    $this->assertDatabaseCount('job_posting_channels', 1);

    $channel = JobPostingChannel::query()->first();
    expect($channel->job_posting_id)->toBe($jobPosting->id)
        ->and($channel->channel)->toBe('company_career_page')
        ->and($channel->status)->toBe(JobPostingChannelStatus::Dispatched)
        ->and($channel->dispatched_at)->not->toBeNull();
});

test('dispatch_calledTwiceForSamePosting_updatesRowInsteadOfDuplicating', function () {
    // Arrange: updateOrCreate is keyed by [job_posting_id, channel], so calling
    // dispatch() twice for the same posting must update the existing log row,
    // not insert a second one.
    $jobPosting = JobPosting::factory()->create();
    $dispatcher = new JobPostingDispatcher;

    // Act
    $dispatcher->dispatch($jobPosting);
    $firstDispatchedAt = JobPostingChannel::query()->first()->dispatched_at;

    $dispatcher->dispatch($jobPosting);

    // Assert
    $this->assertDatabaseCount('job_posting_channels', 1);

    $channel = JobPostingChannel::query()->first();
    expect($channel->status)->toBe(JobPostingChannelStatus::Dispatched)
        ->and($firstDispatchedAt)->not->toBeNull();
});
