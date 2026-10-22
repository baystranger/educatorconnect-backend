<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Administrator view of every account: search, filter, suspend and reinstate.
 * Guarded by role:administrator.
 */
class UserController extends Controller
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['nullable', Rule::in(array_column(UserRole::cases(), 'value'))],
            'status' => ['nullable', Rule::in(['active', 'deactivated', 'suspended'])],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->when($data['role'] ?? null, fn (Builder $q, $role) => $q->where('role', $role))
            ->when($data['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($data['q'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->latest('id')
            ->paginate($data['per_page'] ?? 25)
            ->withQueryString();

        return $this->ok($users->through(fn (User $user) => $this->present($user)), meta: [
            'counts' => User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
        ]);
    }

    public function action(Request $request, User $user, string $action): JsonResponse
    {
        abort_if((int) $user->id === (int) $request->user()->id, 422, 'You cannot change the status of your own account.');
        abort_if($user->isAdministrator() && $action === 'suspend', 422, 'Administrator accounts cannot be suspended here.');

        $before = $user->only(['status']);
        DB::transaction(function () use ($user, $action): void {
            if ($action === 'suspend') {
                $user->forceFill(['status' => 'suspended'])->save();
                // Sign the person out everywhere: API tokens and browser sessions.
                $user->tokens()->delete();
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }
            } else {
                $user->forceFill(['status' => 'active', 'deactivated_at' => null])->save();
            }
        });
        $this->audit->recordChange($action === 'suspend' ? 'user.suspended' : 'user.reinstated', $user, $before, $user->only(['status']));

        return $this->ok($this->present($user->refresh()), $action === 'suspend' ? 'Account suspended.' : 'Account reinstated.');
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'email_verified' => $user->email_verified_at !== null,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
