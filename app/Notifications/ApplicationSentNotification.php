<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Notifications\Messages\MailMessage;

/** Confirms to the educator that their application reached the centre. */
class ApplicationSentNotification extends PlatformNotification
{
    public function __construct(public JobApplication $application) {}

    protected function preferenceType(): string
    {
        return 'application_updates';
    }

    protected function payload(): array
    {
        $this->application->loadMissing('job.centre');
        $centre = $this->application->job->centre->name;

        return [
            'title' => "Your application for {$this->application->job->title} was sent to {$centre}.",
            'href' => '/educator/my-jobs?application='.$this->application->id,
            'initials' => self::initials($centre),
            'tone' => 'green',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return $this->mailFor($notifiable)
            ->subject('Application sent: '.$this->application->job->title)
            ->line($payload['title'])
            ->line('We will let you know as soon as the centre views it, replies or moves it to the next stage.')
            ->action('Track your application', $this->frontendUrl($payload['href']));
    }
}
