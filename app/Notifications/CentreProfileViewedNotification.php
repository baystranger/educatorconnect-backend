<?php

namespace App\Notifications;

use App\Models\ChildcareCentre;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/** Tells a centre that a signed-in educator (or other member) viewed its public profile, at most once per viewer per day. */
class CentreProfileViewedNotification extends PlatformNotification
{
    public function __construct(public ChildcareCentre $centre, public User $viewer) {}

    protected function preferenceType(): string
    {
        return 'profile_views';
    }

    protected function payload(): array
    {
        $who = $this->viewer->isEducator() ? "Educator {$this->viewer->name}" : $this->viewer->name;

        return [
            'title' => "{$who} viewed {$this->centre->name}’s profile.",
            'href' => '/centre/dashboard',
            'initials' => self::initials($this->viewer->name),
            'tone' => 'green',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return $this->mailFor($notifiable)
            ->subject($this->viewer->name.' viewed your centre profile')
            ->line($payload['title'])
            ->action('Open your dashboard', $this->frontendUrl($payload['href']));
    }
}
