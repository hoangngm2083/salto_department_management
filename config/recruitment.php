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

    /*
    |--------------------------------------------------------------------------
    | Resume Upload
    |--------------------------------------------------------------------------
    |
    | Disk 'local' (private, storage/app/private) - resumes contain PII and
    | must never be served from a publicly browsable disk. Same shape as
    | config/imports.php's file-upload settings.
    */

    'resume' => [
        'disk' => env('RECRUITMENT_RESUME_DISK', 'local'),
        'allowed_extensions' => explode(',', (string) env('RECRUITMENT_RESUME_ALLOWED_EXTENSIONS', 'pdf,doc,docx')),
        'max_file_size_mb' => (int) env('RECRUITMENT_RESUME_MAX_FILE_SIZE_MB', 5),
    ],

];
