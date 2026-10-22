<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes `audit_logs`: who did what to which record, with the values before and after.
 * Used for moderation decisions and account security changes, so an administrator can
 * answer "who changed this and when" without reading server logs. Never pass secrets.
 */
class AuditTrail
{
    public function record(string $action, ?Model $subject = null, array $old = [], array $new = [], ?User $actor = null): AuditLog
    {
        $actor ??= Auth::user();
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'actor_user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
        ]);
    }

    /** Records only the attributes that actually differ between $before and $after. */
    public function recordChange(string $action, Model $subject, array $before, array $after, ?User $actor = null): ?AuditLog
    {
        $changed = array_keys(array_filter($after, fn ($value, $key) => ($before[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));
        if ($changed === []) {
            return null;
        }

        return $this->record($action, $subject, array_intersect_key($before, array_flip($changed)), array_intersect_key($after, array_flip($changed)), $actor);
    }
}
