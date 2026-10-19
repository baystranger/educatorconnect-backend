<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SocialRegisterRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    private const USER_RELATIONS = ['educatorProfile.studentProfile', 'childcareCentres', 'socialAccounts'];

    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated());

        return $this->sessionResponse($request, $user, 'Account created successfully.', 201, $request->input('device_name', 'web'));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->auth->authenticate($data['email'], $data['password']);

        return $this->sessionResponse($request, $user, 'Signed in successfully.', 200, $data['device_name'] ?? 'web', $data['remember'] ?? false);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Same response whether or not the account exists, to avoid account enumeration.
        Password::sendResetLink($request->validated());

        return $this->ok(null, 'If an account exists for that email, password reset instructions will be sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return $this->ok(null, 'Password reset successfully.');
    }

    public function exchangeOAuthCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:64'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ]);
        $user = $this->auth->exchangeOAuthCode($data['code']);

        return $this->sessionResponse($request, $user, 'Signed in successfully.', 200, $data['device_name'] ?? 'web');
    }

    /**
     * Starts an OAuth flow. Intents: login, signup (with role), or connect (link to the
     * already signed-in account from Settings).
     */
    public function oauthRedirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, SocialAccount::PROVIDERS, true), 404);

        $data = $request->validate([
            'intent' => ['required', 'in:login,signup,connect'],
            'role' => ['required_if:intent,signup', 'nullable', 'in:educator,childcare_centre,professional'],
        ]);

        $frontendUrl = rtrim(config('frontend.url'), '/');

        if (! config("services.{$provider}.client_id") || ! config("services.{$provider}.client_secret")) {
            return redirect()->away($this->oauthErrorBase($data['intent'], $data['role'] ?? null).'unavailable');
        }

        $connectUserId = null;
        if ($data['intent'] === 'connect') {
            $connectUserId = Auth::guard('web')->id();
            if (! $connectUserId) {
                return redirect()->away($frontendUrl.'/signin?oauth_error=expired');
            }
        }

        $request->session()->put('oauth_flow', [
            'intent' => $data['intent'],
            'role' => $data['role'] ?? null,
            'user_id' => $connectUserId,
        ]);

        return Socialite::driver($provider)->redirect();
    }

    public function oauthCallback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, SocialAccount::PROVIDERS, true), 404);

        $frontendUrl = rtrim(config('frontend.url'), '/');
        $flow = $request->session()->pull('oauth_flow');
        $isConnect = ($flow['intent'] ?? null) === 'connect';
        $errorBase = $this->oauthErrorBase($flow['intent'] ?? 'login', $flow['role'] ?? null);

        if ($request->filled('error')) {
            return redirect()->away($errorBase.'cancelled');
        }
        if (! is_array($flow) || ! in_array($flow['intent'] ?? null, ['login', 'signup', 'connect'], true)) {
            return redirect()->away($frontendUrl.'/signin?oauth_error=expired');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            Log::warning('OAuth provider callback failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return redirect()->away($errorBase.'expired');
        }

        try {
            if ($isConnect) {
                $user = User::query()->findOrFail($flow['user_id']);
                $this->auth->linkProvider($user, $provider, $socialUser);

                return redirect()->away($frontendUrl.'/settings?section=security&connected='.$provider);
            }

            $user = $this->auth->authenticateWithProvider($provider, $socialUser, $flow['role'] ?? null, $flow['intent']);

            // New identity on sign-up: return to the sign-up form, connected, to add location details.
            // The token and the name/email to show travel in the URL fragment: the page renders
            // without another API round trip, and fragments never reach server or proxy logs.
            if (! $user) {
                [$token, $pending] = $this->auth->startPendingSignup($provider, $socialUser, $flow['role'] ?? null);

                return redirect()->away($frontendUrl.'/signup?type='.$this->signupType($flow['role'] ?? null).'#'.http_build_query([
                    'oauth' => $token,
                    'provider' => $pending->provider,
                    'role' => $pending->role,
                    'email' => $pending->email,
                    'name' => $pending->name,
                    'first_name' => $pending->first_name,
                    'last_name' => $pending->last_name,
                    'expires_at' => $pending->expires_at->toIso8601String(),
                ], '', '&', PHP_QUERY_RFC3986));
            }
        } catch (ValidationException|AuthorizationException) {
            return redirect()->away($errorBase.'account_conflict');
        }

        $code = $this->auth->createOAuthCode($user);

        return redirect()->away($frontendUrl.'/auth/callback?code='.urlencode($code));
    }

    /** The provider profile behind a pending Google/LinkedIn sign-up (shown as "Connected with …"). */
    public function pendingSignup(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $pending = $this->auth->findPendingSignup($data['token']);

        if (! $pending) {
            throw ValidationException::withMessages([
                'token' => 'Your Google or LinkedIn connection expired. Please connect again.',
            ]);
        }

        return $this->ok([
            'provider' => $pending->provider,
            'role' => $pending->role,
            'email' => $pending->email,
            'name' => $pending->name,
            'first_name' => $pending->first_name,
            'last_name' => $pending->last_name,
            'expires_at' => $pending->expires_at->toIso8601String(),
        ]);
    }

    public function registerSocial(SocialRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->auth->completeSocialSignup($data['token'], $data);

        return $this->sessionResponse($request, $user, 'Account created successfully.', 201, $data['device_name'] ?? 'web');
    }

    /** Where an OAuth failure lands: Settings for connect, back to the sign-up form for sign-up, otherwise sign-in. */
    private function oauthErrorBase(string $intent, ?string $role): string
    {
        $frontendUrl = rtrim(config('frontend.url'), '/');

        return match ($intent) {
            'connect' => $frontendUrl.'/settings?section=security&oauth_error=',
            'signup' => $frontendUrl.'/signup?type='.$this->signupType($role).'&oauth_error=',
            default => $frontendUrl.'/signin?oauth_error=',
        };
    }

    /** API role → the sign-up page's ?type= value. */
    private function signupType(?string $role): string
    {
        return match ($role) {
            'childcare_centre' => 'centre',
            'professional' => 'professional',
            default => 'educator',
        };
    }

    public function disconnectProvider(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, SocialAccount::PROVIDERS, true), 404);
        $this->auth->unlinkProvider($request->user(), $provider);

        return $this->ok(
            AuthUserResource::make($request->user()->refresh()->load(self::USER_RELATIONS)),
            ucfirst($provider).' disconnected.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();
        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->ok(null, 'Signed out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(AuthUserResource::make($request->user()->load(self::USER_RELATIONS)));
    }

    /** The signed-in account, or null for guests. */
    public function session(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        if ($user && $user->status !== 'active') {
            $user = null;
        }

        return $this->ok($user ? AuthUserResource::make($user->load(self::USER_RELATIONS)) : null);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->ok(null, 'Your email is already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->ok(null, 'Verification email sent. Please check your inbox.');
    }

    /**
     * Target of the signed link in the verification email. Redirects back to the frontend.
     */
    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        $frontendUrl = rtrim(config('frontend.url'), '/');
        $user = User::query()->find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->away($frontendUrl.'/verify-email?status=invalid');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->away($frontendUrl.'/verify-email?status=verified');
    }

    private function sessionResponse(Request $request, User $user, string $message, int $status, string $deviceName, bool $remember = false): JsonResponse
    {
        if ($request->hasSession()) {
            Auth::guard('web')->login($user, $remember);
            $request->session()->regenerate();
            $token = null;
        } else {
            $token = $this->auth->issueToken($user, $deviceName);
        }

        return $this->ok([
            'user' => AuthUserResource::make($user->load(self::USER_RELATIONS))->resolve($request),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], $message, $status);
    }
}
