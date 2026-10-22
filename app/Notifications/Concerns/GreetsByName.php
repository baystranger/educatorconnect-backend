<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use App\Support\PersonName;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Every email opens with the recipient's real name: "Hello, Rohit Gautam,".
 *
 * Users are greeted by the person who holds the account (first + last name), not the
 * account display name — for a centre account that display name is the centre itself.
 * Parents have no account; their name comes from the mail route
 * (Notification::route('mail', [$email => $name])).
 */
trait GreetsByName
{
    protected function mailFor(object $notifiable): MailMessage
    {
        return (new MailMessage)->greeting(self::greetingFor($notifiable));
    }

    protected static function greetingFor(object $notifiable): string
    {
        $name = self::recipientName($notifiable);

        return $name === '' ? 'Hello,' : "Hello, {$name},";
    }

    protected static function recipientName(object $notifiable): string
    {
        if ($notifiable instanceof User) {
            return PersonName::join($notifiable->first_name, $notifiable->last_name) ?: trim((string) $notifiable->name);
        }

        if ($notifiable instanceof AnonymousNotifiable) {
            $route = $notifiable->routes['mail'] ?? null;
            if (is_array($route)) {
                $name = reset($route);

                return is_string($name) && ! filter_var($name, FILTER_VALIDATE_EMAIL) ? trim($name) : '';
            }
        }

        return trim((string) ($notifiable->name ?? ''));
    }
}
