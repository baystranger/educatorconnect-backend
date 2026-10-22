<?php

namespace App\Notifications;

use App\Models\ChildcareCentre;
use Illuminate\Notifications\Messages\MailMessage;

/** Tells an educator that a centre viewed their profile (at most once per centre per day). */
class ProfileViewedNotification extends PlatformNotification
{
    public function __construct(public ChildcareCentre $centre) {}

    protected function preferenceType(): string
    {
        return 'profile_views';
    }

    protected function payload(): array
    {
        return [
            'title' => $this->centre->name.' viewed your profile.',
            'href' => '/educator/profile',
            'initials' => self::initials($this->centre->name),
            'tone' => 'green',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->mailFor($notifiable)
            ->subject($this->centre->name.' viewed your profile')
            ->line($this->centre->name.' viewed your Educator Connect profile.')
            ->action('Polish your profile', $this->frontendUrl('/educator/profile'));
    }
}
