<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Notifications\Messages\MailMessage;

/** Sent to the educator when a centre moves their application to a new stage. */
class ApplicationStatusChangedNotification extends PlatformNotification
{
    public function __construct(public JobApplication $application) {}

    protected function preferenceType(): string
    {
        return $this->application->status === 'interview' ? 'interview_requests' : 'application_updates';
    }

    protected function payload(): array
    {
        $this->application->loadMissing('job.centre');
        $centre = $this->application->job->centre->name;
        $label = config('platform.application_statuses.'.$this->application->status, $this->application->status);

        $title = match ($this->application->status) {
            'interview' => "{$centre} invited you to interview for {$this->application->job->title}.",
            'offered' => "{$centre} made you an offer for {$this->application->job->title}.",
            'hired' => "Congratulations — {$centre} marked you as hired.",
            'not_selected' => "{$centre} updated your application for {$this->application->job->title}.",
            default => "{$centre} moved your application to {$label}.",
        };

        return [
            'title' => $title,
            'href' => '/educator/my-jobs?application='.$this->application->id,
            'initials' => self::initials($centre),
            'tone' => 'yellow',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();
        $mail = $this->mailFor($notifiable)->subject($payload['title'])->line($payload['title']);

        if ($this->application->status === 'interview' && $this->application->interview_at) {
            $mail->line('When: '.$this->application->interview_at->timezone(config('platform.timezone'))->format('l, M j \a\t g:i A'));
            if ($this->application->interview_details) {
                $mail->line($this->application->interview_details);
            }
        }

        return $mail->action('View application', $this->frontendUrl($payload['href']));
    }
}
