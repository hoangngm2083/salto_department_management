<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reminder Window
    |--------------------------------------------------------------------------
    |
    | How many days before a pending leave request's start_date the reminder
    | scheduler should notify the department's manager(s), if it's still
    | unreviewed.
    |
    */

    'reminder_days_before_start' => (int) env('LEAVE_REQUEST_REMINDER_DAYS', 2),

    /*
    |--------------------------------------------------------------------------
    | Reminder Send Time
    |--------------------------------------------------------------------------
    |
    | Time of day (HH:MM) the leave-requests:send-reminders command runs via
    | the scheduler.
    |
    */

    'reminder_cron' => env('LEAVE_REQUEST_REMINDER_CRON', '0 8 * * *'),

];
