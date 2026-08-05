<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class LeaveRequestReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $total) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $requests = Str::plural('leave request', $this->total);
        $verb = $this->total === 1 ? 'is' : 'are';

        return (new MailMessage)
            ->subject('Pending Leave Requests Need Review')
            ->greeting("Hi {$notifiable->name},")
            ->line("Your department has {$this->total} pending {$requests} starting soon that {$verb} still awaiting your review.")
            ->line('Please review them soon so they can be resolved before the leave begins.');
    }
}
