<?php

namespace App\Services\Recruitment\Contracts;

use App\Models\JobPosting;
use App\Services\Recruitment\ChannelDispatchResult;

interface JobPostingChannel
{
    /**
     * The config key this channel is registered under (`config('recruitment.channels')`).
     */
    public function key(): string;

    public function dispatch(JobPosting $jobPosting): ChannelDispatchResult;
}
