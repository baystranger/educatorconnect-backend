<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the educator that the centre opened their application for the first time.
 * In-app only: an email for every first look would be noise.
 */
class ApplicationViewedNotification extends PlatformNotification
{
    public function __construct(public JobApplication $application) {}

    protected function preferenceType(): string
    {
        return 'application_updates';
    }

    public function via(object $notifiable): array
    {
        return array_values(array_intersect(parent::via($notifiable), ['database']));
    }

    protected function payload(): array
    {
        $this->application->loadMissing('job.centre');
        $centre = $this->application->job->centre->name;

        return [
            'title' => "{$centre} viewed your application for {$this->application->job->title}.",
            'href' => '/educator/my-jobs?application='.$this->application->id,
            'initials' => self::initials($centre),
            'tone' => 'green',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return $this->mailFor($notifiable)->subject($payload['title'])->line($payload['title']);
    }
}
