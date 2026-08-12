<?php

namespace App\Services\Recruitment\Channels;

use App\Models\JobPosting;
use App\Services\Recruitment\ChannelDispatchResult;
use App\Services\Recruitment\Contracts\JobPostingChannel;

/**
 * No external job board API exists yet, so dispatching here doesn't call out anywhere -
 * "appearing on the company career page" already happens by virtue of the posting being
 * `published` and visible through the public careers endpoint (Phase R2). The interface
 * leaves room for a real HTTP call (and a real `external_ref`) later without changing
 * JobPostingDispatcher or any caller.
 */
class CompanyCareerPageChannel implements JobPostingChannel
{
    public function key(): string
    {
        return 'company_career_page';
    }

    public function dispatch(JobPosting $jobPosting): ChannelDispatchResult
    {
        return ChannelDispatchResult::dispatched();
    }
}
