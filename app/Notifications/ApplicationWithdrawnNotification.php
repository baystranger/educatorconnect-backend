<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Notifications\Messages\MailMessage;

/** Sent to the centre owner when an educator withdraws their application. */
class ApplicationWithdrawnNotification extends PlatformNotification
{
    public function __construct(public JobApplication $application) {}

    protected function preferenceType(): string
    {
        return 'applicant_activity';
    }

    protected function payload(): array
    {
        $this->application->loadMissing('job', 'educator');
        $name = $this->application->educator->name;

        return [
            'title' => "{$name} withdrew their application for {$this->application->job->title}.",
            'href' => "/centre/applicants?job={$this->application->job_id}&application={$this->application->id}",
            'initials' => self::initials($name),
            'tone' => 'yellow',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return $this->mailFor($notifiable)
            ->subject('Application withdrawn: '.$this->application->job->title)
            ->line($payload['title'])
            ->action('View applicants', $this->frontendUrl($payload['href']));
    }
}
