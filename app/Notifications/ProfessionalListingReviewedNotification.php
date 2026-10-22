<?php

namespace App\Notifications;

use App\Models\ProfessionalListing;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to a professional when an administrator approves or rejects their listing,
 * or approves their claim on an existing listing.
 */
class ProfessionalListingReviewedNotification extends PlatformNotification
{
    public function __construct(public ProfessionalListing $listing, public bool $approved, public ?string $notes = null) {}

    protected function preferenceType(): string
    {
        return 'listing_review';
    }

    protected function payload(): array
    {
        return [
            'title' => $this->approved
                ? $this->listing->name.' is live in the professional directory.'
                : $this->listing->name.' needs changes before it can go live.',
            'href' => '/professional',
            'initials' => $this->approved ? '✓' : '!',
            'tone' => $this->approved ? 'green' : 'danger',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = $this->mailFor($notifiable)->subject($this->approved
            ? $this->listing->name.' is live on Educator Connect'
            : 'Your Educator Connect listing needs changes');

        if ($this->approved) {
            $mail->line('Your directory listing has been approved. Childcare centres can now find and contact you.');
        } else {
            $mail->line('An administrator reviewed your directory listing and it needs a few changes before it can go live.');
            if ($this->notes) {
                $mail->line('Reviewer notes: '.$this->notes);
            }
        }

        return $mail->action('Open your listings', $this->frontendUrl('/professional'));
    }
}
