<?php

namespace App\Notifications;

use App\Models\ChildcareCentre;
use Illuminate\Notifications\Messages\MailMessage;

class CentreProfilePublishedNotification extends PlatformNotification
{
    public function __construct(public ChildcareCentre $centre) {}

    protected function preferenceType(): string
    {
        return 'profile_review';
    }

    protected function payload(): array
    {
        return [
            'title' => 'Your profile changes were approved and published.',
            'href' => '/centre/profile/basics',
            'initials' => '✓',
            'tone' => 'green',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->mailFor($notifiable)
            ->subject($this->centre->name.' is live on Educator Connect')
            ->line('Your centre profile is published. Families can now find you, ask questions, book tours and join your waitlist.')
            ->action('View your profile', $this->frontendUrl('/childcare/'.$this->centre->slug));
    }
}
