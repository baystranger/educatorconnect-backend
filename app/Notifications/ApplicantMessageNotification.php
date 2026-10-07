<?php

namespace App\Notifications;

use App\Models\JobApplication;
use App\Models\Message;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** A message on a job application thread, sent to the other party. */
class ApplicantMessageNotification extends PlatformNotification
{
    public function __construct(public JobApplication $application, public Message $message) {}

    protected function preferenceType(): string
    {
        return $this->message->sender_role === Message::ROLE_CENTRE ? 'application_updates' : 'applicant_messages';
    }

    protected function payload(): array
    {
        $this->application->loadMissing('job.centre', 'educator');
        $fromCentre = $this->message->sender_role === Message::ROLE_CENTRE;
        $from = $fromCentre ? $this->application->job->centre->name : $this->application->educator->name;

        return [
            'title' => "New message from {$from}: ".Str::limit($this->message->body, 80),
            'href' => $fromCentre
                ? '/educator/my-jobs?application='.$this->application->id
                : "/centre/applicants?job={$this->application->job_id}&application={$this->application->id}",
            'initials' => self::initials($from),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return (new MailMessage)
            ->subject('New message about '.$this->application->job->title)
            ->line($this->message->body)
            ->action('Open conversation', $this->frontendUrl($payload['href']));
    }
}
