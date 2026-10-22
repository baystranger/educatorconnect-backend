<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notifications (the bell in the signed-in top nav).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'filter' => ['nullable', 'in:all,unread'],
        ]);
        $user = $request->user();

        $query = $request->input('filter') === 'unread' ? $user->unreadNotifications() : $user->notifications();
        $page = $query->latest()->paginate($request->integer('per_page', 10));

        return $this->ok(
            $page->through(fn (DatabaseNotification $n) => $this->present($n)),
            meta: ['unread_count' => $user->unreadNotifications()->count()],
        );
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return $this->ok($this->present($notification), 'Marked as read.', meta: [
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->ok(null, 'All notifications marked as read.', meta: ['unread_count' => 0]);
    }

    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => $notification->data['kind'] ?? null,
            'title' => $notification->data['title'] ?? '',
            'href' => $notification->data['href'] ?? null,
            'initials' => $notification->data['initials'] ?? null,
            'tone' => $notification->data['tone'] ?? 'purple',
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
