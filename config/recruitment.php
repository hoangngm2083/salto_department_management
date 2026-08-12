<?php

use App\Services\Recruitment\Channels\CompanyCareerPageChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Job Posting Channel => Class Map
    |--------------------------------------------------------------------------
    |
    | Adding a real external channel later only requires a new class implementing
    | JobPostingChannel plus an entry here and in `active_channels` below — no
    | changes to JobPostingDispatcher.
    */

    'channels' => [
        'company_career_page' => CompanyCareerPageChannel::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Active Channels
    |--------------------------------------------------------------------------
    |
    | JobPostingDispatcher dispatches to every channel key listed here when a
    | job posting is published.
    */

    'active_channels' => ['company_career_page'],

];
