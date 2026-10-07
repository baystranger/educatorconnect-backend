<?php

namespace App\Notifications;

use App\Models\ParentRequest;
use Illuminate\Notifications\Messages\MailMessage;

/** Sent to the centre owner when a family sends an inquiry, tour request or waitlist sign-up. */
class ParentRequestReceivedNotification extends PlatformNotification
{
    public function __construct(public ParentRequest $requestItem) {}

    protected function preferenceType(): string
    {
        return match ($this->requestItem->type) {
            'tour' => 'tour_requests',
            'waitlist' => 'waitlist',
            default => 'parent_inquiries',
        };
    }

    protected function payload(): array
    {
        $name = $this->requestItem->parent_name;
        $title = match ($this->requestItem->type) {
            'tour' => "New tour request from {$name}.",
            'waitlist' => "{$name} joined your waitlist.",
            default => "New question from {$name}.",
        };

        return [
            'title' => $title,
            'href' => '/centre/families?tab='.$this->requestItem->type.'&request='.$this->requestItem->id,
            'initials' => match ($this->requestItem->type) { 'tour' => 'T', 'waitlist' => 'W', default => '?' },
            'tone' => $this->requestItem->type === 'tour' ? 'yellow' : 'purple',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return (new MailMessage)
            ->subject($payload['title'])
            ->line($payload['title'])
            ->line('Reply from your Families inbox — parents get an email copy of every reply.')
            ->action('Open Families', $this->frontendUrl($payload['href']));
    }
}
