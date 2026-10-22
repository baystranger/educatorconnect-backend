<?php

namespace App\Notifications;

use App\Notifications\Concerns\GreetsByName;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Email verification link that hits the API's signed verify route, which then
 * redirects the browser back to the frontend.
 */
class VerifyEmailNotification extends VerifyEmail
{
    use GreetsByName;

    public function toMail($notifiable)
    {
        return parent::toMail($notifiable)->greeting(self::greetingFor($notifiable));
    }

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
