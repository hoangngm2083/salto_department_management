<?php

namespace App\Services\Recruitment;

use App\Enums\JobPostingChannelStatus;
use App\Models\JobPosting;
use App\Models\JobPostingChannel as JobPostingChannelLog;
use App\Services\Recruitment\Contracts\JobPostingChannel;

class JobPostingDispatcher
{
    /**
     * Dispatch the posting to every channel listed in `config('recruitment.active_channels')`,
     * logging one `job_posting_channels` row per channel. Channels decide their own
     * success/failure and always return a ChannelDispatchResult rather than throwing.
     *
     * Runs synchronously on the caller's request thread - harmless today since
     * CompanyCareerPageChannel does no I/O, but once a channel makes a real outbound
     * HTTP call, queue this (e.g. dispatch a ShouldQueue job per channel) so a slow/down
     * channel can't block the publish response.
     */
    public function dispatch(JobPosting $jobPosting): void
    {
        foreach (config('recruitment.active_channels', []) as $channelKey) {
            $channelClass = config("recruitment.channels.{$channelKey}");

            if ($channelClass === null) {
                continue;
            }

            /** @var JobPostingChannel $channel */
            $channel = app($channelClass);
            $result = $channel->dispatch($jobPosting);

            JobPostingChannelLog::query()->updateOrCreate(
                ['job_posting_id' => $jobPosting->id, 'channel' => $channel->key()],
                [
                    'status' => $result->status,
                    'external_ref' => $result->externalRef,
                    'error_message' => $result->errorMessage,
                    'dispatched_at' => $result->status === JobPostingChannelStatus::Dispatched ? now() : null,
                ]
            );
        }
    }
}
