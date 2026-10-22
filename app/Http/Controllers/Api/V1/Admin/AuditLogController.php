<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only activity history: moderation decisions and account security changes.
 */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'actor_user_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->with('actor:id,name,email,role')
            // "centre." matches every centre action; "centre.approved" one action.
            ->when($data['action'] ?? null, fn (Builder $q, $action) => str_ends_with($action, '.')
                ? $q->where('action', 'like', $action.'%')
                : $q->where('action', $action))
            ->when($data['actor_user_id'] ?? null, fn (Builder $q, $id) => $q->where('actor_user_id', $id))
            ->latest('id')
            ->paginate($data['per_page'] ?? 50)
            ->withQueryString();

        return $this->ok($logs->through(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'subject' => $log->auditable_type ? ['type' => $log->auditable_type, 'id' => $log->auditable_id] : null,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'actor' => $log->actor?->only(['id', 'name', 'email', 'role']),
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ]));
    }
}
