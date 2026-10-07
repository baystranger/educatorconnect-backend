<?php

namespace App\Notifications;

use App\Models\ParentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Confirms to a parent that their request reached the centre. */
class ParentRequestConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ParentRequest $requestItem) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $centre = $this->requestItem->centre;
        $what = match ($this->requestItem->type) {
            'tour' => 'tour request',
            'waitlist' => 'waitlist request',
            default => 'question',
        };

        return (new MailMessage)
            ->subject("We sent your {$what} to {$centre->name}")
            ->greeting('Hi '.$this->requestItem->parent_name.',')
            ->line("Your {$what} is with {$centre->name}. Centres usually reply within a day, and their reply will arrive at this email address.")
            ->line('You did not need an account — we only shared the details you entered with this centre.');
    }
}
