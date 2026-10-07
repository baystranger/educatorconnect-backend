<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangeEmailRequest;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\DeactivateAccountRequest;
use App\Http\Requests\Account\NotificationPreferencesRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\AuthUserResource;
use App\Services\AccountService;
use App\Services\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings: account details, email, password, notifications, privacy export, deactivation.
 */
class AccountController extends Controller
{
    private const USER_RELATIONS = ['educatorProfile.studentProfile', 'childcareCentres', 'socialAccounts'];

    public function __construct(
        private readonly AccountService $accounts,
        private readonly NotificationPreferences $preferences,
    ) {}

    public function update(UpdateAccountRequest $request): JsonResponse
    {
        $user = $this->accounts->updateDetails($request->user(), $request->validated());

        return $this->ok(AuthUserResource::make($user->load(self::USER_RELATIONS)), 'Changes saved.');
    }

    public function changeEmail(ChangeEmailRequest $request): JsonResponse
    {
        $user = $this->accounts->changeEmail($request->user(), $request->validated('email'), $request->validated('current_password'));

        return $this->ok(
            AuthUserResource::make($user->load(self::USER_RELATIONS)),
            'Email updated. Check your inbox to verify the new address.',
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $this->accounts->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
            $token instanceof PersonalAccessToken ? (string) $token->id : null,
        );

        if ($request->hasSession()) {
            // Keep this browser signed in with the new password hash.
            Auth::guard('web')->login($request->user()->refresh());
            $request->session()->regenerate();
        }

        return $this->ok(null, 'Password updated.');
    }

    public function notificationPreferences(Request $request): JsonResponse
    {
        return $this->ok([
            'definitions' => collect($this->preferences->definitions($request->user()))
                ->map(fn ($definition, $key) => ['key' => $key, 'label' => $definition['label'], 'description' => $definition['description']])
                ->values(),
            'preferences' => $this->preferences->forUser($request->user()),
        ]);
    }

    public function updateNotificationPreferences(NotificationPreferencesRequest $request): JsonResponse
    {
        return $this->ok($this->preferences->update($request->user(), $request->validated()), 'Notification settings saved.');
    }

    public function export(Request $request): Response
    {
        $filename = 'educator-connect-data-'.now()->format('Y-m-d').'.json';

        return response()->json($this->accounts->export($request->user()), 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function deactivate(DeactivateAccountRequest $request): JsonResponse
    {
        $this->accounts->deactivate($request->user(), $request->validated('password'));

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->ok(null, 'Your account has been deactivated. Sign in within 30 days to reactivate it.');
    }
}
