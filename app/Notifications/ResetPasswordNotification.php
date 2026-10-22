<?php

namespace App\Notifications;

use App\Notifications\Concerns\GreetsByName;
use Illuminate\Auth\Notifications\ResetPassword;

/** Password reset link; the URL points at the frontend (see AppServiceProvider). */
class ResetPasswordNotification extends ResetPassword
{
    use GreetsByName;

    public function toMail($notifiable)
    {
        return parent::toMail($notifiable)->greeting(self::greetingFor($notifiable));
    }
}
