<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;

/**
 * Resolves per-user, per-type email / in-app preferences with role defaults
 * from config('platform.notification_types').
 */
class NotificationPreferences
{
    public function definitions(User $user): array
    {
        return config('platform.notification_types.'.$user->role, []);
    }

    /**
     * @return array{types: array<string, array{email: bool, in_app: bool}>, job_alert_frequency: string}
     */
    public function forUser(User $user): array
    {
        $stored = $user->notificationPreference?->preferences ?? [];
        $types = [];
        foreach ($this->definitions($user) as $key => $definition) {
            $types[$key] = [
                'email' => (bool) ($stored['types'][$key]['email'] ?? $definition['default'][0]),
                'in_app' => (bool) ($stored['types'][$key]['in_app'] ?? $definition['default'][1]),
            ];
        }

        return [
            'types' => $types,
            'job_alert_frequency' => $stored['job_alert_frequency'] ?? 'daily',
        ];
    }

    public function update(User $user, array $data): array
    {
        $current = $this->forUser($user);
        foreach ($data['types'] ?? [] as $key => $channels) {
            if (isset($current['types'][$key])) {
                $current['types'][$key] = [
                    'email' => (bool) ($channels['email'] ?? $current['types'][$key]['email']),
                    'in_app' => (bool) ($channels['in_app'] ?? $current['types'][$key]['in_app']),
                ];
            }
        }
        if (isset($data['job_alert_frequency'])) {
            $current['job_alert_frequency'] = $data['job_alert_frequency'];
        }

        NotificationPreference::updateOrCreate(['user_id' => $user->id], ['preferences' => $current]);
        $user->unsetRelation('notificationPreference');

        return $current;
    }

    /**
     * Channels a notification of the given type should use for this user.
     * Unknown types fall back to both channels.
     */
    public function channels(User $user, string $type): array
    {
        $prefs = $this->forUser($user)['types'][$type] ?? ['email' => true, 'in_app' => true];
        $channels = [];
        if ($prefs['in_app']) {
            $channels[] = 'database';
        }
        if ($prefs['email']) {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
