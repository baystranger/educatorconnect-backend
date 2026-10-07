<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Notifications\Messages\MailMessage;

/** Sent to the centre owner when an educator applies. */
class JobApplicationSubmittedNotification extends PlatformNotification
{
    public function __construct(public JobApplication $application) {}

    protected function preferenceType(): string
    {
        return 'new_applicants';
    }

    protected function payload(): array
    {
        $this->application->loadMissing('job', 'educator');
        $name = $this->application->educator->name;

        return [
            'title' => "{$name} applied to {$this->application->job->title}.",
            'href' => "/centre/applicants?job={$this->application->job_id}&application={$this->application->id}",
            'initials' => self::initials($name),
            'tone' => 'green',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return (new MailMessage)
            ->subject('New applicant: '.$this->application->job->title)
            ->greeting('You have a new applicant')
            ->line($payload['title'])
            ->action('Review applicant', $this->frontendUrl($payload['href']));
    }
}
