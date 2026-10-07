<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base class for in-app + email notifications. Channels follow the recipient's
 * preferences for {@see preferenceType()}; the database payload is what the
 * notification bell renders.
 */
abstract class PlatformNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract protected function preferenceType(): string;

    /**
     * @return array{title: string, href: string, initials?: string, tone?: string}
     */
    abstract protected function payload(): array;

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        return app(NotificationPreferences::class)->channels($notifiable, $this->preferenceType());
    }

    public function toArray(object $notifiable): array
    {
        return array_merge(['kind' => $this->preferenceType(), 'tone' => 'purple'], $this->payload());
    }

    protected function frontendUrl(string $path): string
    {
        return rtrim(config('frontend.url'), '/').'/'.ltrim($path, '/');
    }

    protected static function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $letters = array_map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)), array_slice(array_filter($parts), 0, 2));

        return implode('', $letters) ?: '?';
    }
}
