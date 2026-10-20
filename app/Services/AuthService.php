<?php

namespace App\Services;

use App\Models\ChildcareCentre;
use App\Models\EducatorProfile;
use App\Models\OAuthLoginCode;
use App\Models\OAuthPendingSignup;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\PersonName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Spatie\Permission\Models\Role;

class AuthService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $isCentre = $data['role'] === 'childcare_centre';
            // A centre's account holder is its contact person unless first/last name are sent.
            [$firstName, $lastName] = filled($data['first_name'] ?? null)
                ? [$data['first_name'], $data['last_name'] ?? '']
                : PersonName::split((string) ($data['contact_person'] ?? ''));
            $displayName = $isCentre ? $data['centre_name'] : PersonName::join($firstName, $lastName);

            $user = $this->users->create([
                'name' => $displayName,
                'first_name' => $firstName ?: null,
                'last_name' => $lastName ?: null,
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => $data['role'],
                'status' => 'active',
            ]);

            $this->assignRole($user, $data['role']);
            $this->createRoleProfile($user, $data);

            return $user->refresh();
        });

        $user->sendEmailVerificationNotification();

        return $user;
    }

    public function authenticate(string $email, string $password): User
    {
        $user = $this->users->findByEmail(strtolower($email));

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        $this->ensureCanSignIn($user);
        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }

    /**
     * Active accounts pass. Deactivated accounts are reactivated when they sign in again
     * within the reactivation window; suspended accounts are refused.
     */
    public function ensureCanSignIn(User $user): void
    {
        if ($user->status === 'deactivated') {
            $window = (int) config('platform.account.reactivation_window_days', 30);
            if ($user->deactivated_at && $user->deactivated_at->addDays($window)->isFuture()) {
                $user->forceFill(['status' => 'active', 'deactivated_at' => null])->save();
                $user->educatorProfile?->update(['open_to_work' => true]);
                $user->professionalListings()->onlyTrashed()->restore();

                return;
            }
        }

        if ($user->status !== 'active') {
            throw new AuthorizationException('This account is not active.');
        }
    }

    /**
     * Signs in the account linked to this provider identity. Returns null on a sign-up
     * for an identity we haven't seen: the caller then starts a pending sign-up so the
     * person can add their location details before the account is created.
     */
    public function authenticateWithProvider(
        string $provider,
        SocialiteUser $socialUser,
        ?string $role,
        string $intent,
    ): ?User {
        $user = $this->users->findByOAuthIdentity($provider, (string) $socialUser->getId());

        if ($user) {
            if ($role && $user->role !== $role) {
                throw ValidationException::withMessages([
                    'role' => 'This social account is already registered for a different account type.',
                ]);
            }

            $this->ensureCanSignIn($user);
            $user->forceFill(['last_login_at' => now()])->save();

            return $user;
        }

        if ($intent === 'login') {
            throw ValidationException::withMessages([
                'email' => 'No account is connected to this social profile. Sign up first.',
            ]);
        }

        return null;
    }

    /**
     * Holds the provider profile for up to 30 minutes and returns the single-use token
     * the sign-up page uses to finish the account, with the pending record.
     *
     * @return array{0: string, 1: OAuthPendingSignup}
     */
    public function startPendingSignup(string $provider, SocialiteUser $socialUser, ?string $role): array
    {
        $email = strtolower(trim((string) $socialUser->getEmail()));
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'The social provider did not return an email address.',
            ]);
        }

        if ($this->users->findByEmail($email)) {
            throw ValidationException::withMessages([
                'email' => 'This email already has an account. Sign in with that account before connecting a social provider.',
            ]);
        }

        $raw = $socialUser->getRaw();
        $name = trim((string) ($socialUser->getName() ?: $socialUser->getNickname()));
        $name = $name !== '' ? $name : Str::before($email, '@');
        $nameParts = preg_split('/\s+/', $name, 2) ?: [$name];

        $plainTextToken = Str::random(64);

        $pending = OAuthPendingSignup::create([
            'token_hash' => hash('sha256', $plainTextToken),
            'provider' => $provider,
            'provider_user_id' => (string) $socialUser->getId(),
            'email' => $email,
            'name' => Str::limit($name, 255, ''),
            'first_name' => Str::limit((string) ($raw['given_name'] ?? $raw['localizedFirstName'] ?? $nameParts[0]), 100, ''),
            'last_name' => Str::limit((string) ($raw['family_name'] ?? $raw['localizedLastName'] ?? $nameParts[1] ?? ''), 100, ''),
            'role' => $role ?? 'educator',
            'expires_at' => now()->addMinutes(30),
        ]);

        return [$plainTextToken, $pending];
    }

    /** A still-usable pending sign-up for the token, or null. */
    public function findPendingSignup(string $plainTextToken): ?OAuthPendingSignup
    {
        $pending = OAuthPendingSignup::query()->where('token_hash', hash('sha256', $plainTextToken))->first();

        return $pending?->isUsable() ? $pending : null;
    }

    /**
     * Creates the account for a pending Google/LinkedIn sign-up. Like a form sign-up, the
     * account starts unverified and a verification email is sent.
     */
    public function completeSocialSignup(string $plainTextToken, array $data): User
    {
        $created = false;

        $user = DB::transaction(function () use ($plainTextToken, $data, &$created): User {
            $pending = OAuthPendingSignup::query()
                ->where('token_hash', hash('sha256', $plainTextToken))
                ->lockForUpdate()
                ->first();

            // A retry after a lost response: the account exists, so sign the person in again
            // (its verification email already went out).
            if ($pending?->consumed_at && $pending->user && $pending->expires_at->isFuture()) {
                $this->ensureCanSignIn($pending->user);

                return $pending->user;
            }

            if (! $pending || ! $pending->isUsable()) {
                throw ValidationException::withMessages([
                    'token' => 'Your Google or LinkedIn connection expired. Please connect again.',
                ]);
            }

            if ($pending->role === 'childcare_centre') {
                $missing = array_filter(
                    ['centre_name' => 'centre name', 'phone' => 'phone number', 'address' => 'street address'],
                    fn ($label, $field) => blank($data[$field] ?? null),
                    ARRAY_FILTER_USE_BOTH,
                );
                if ($missing) {
                    throw ValidationException::withMessages(
                        array_map(fn ($label) => "Please enter your {$label}.", $missing),
                    );
                }
            }

            if ($this->users->findByEmail($pending->email)) {
                throw ValidationException::withMessages([
                    'email' => 'This email already has an account. Please sign in instead.',
                ]);
            }

            if (SocialAccount::query()->where('provider', $pending->provider)->where('provider_user_id', $pending->provider_user_id)->exists()) {
                throw ValidationException::withMessages([
                    'token' => 'This '.ucfirst($pending->provider).' account is already connected to an Educator Connect account. Please sign in instead.',
                ]);
            }

            $pending->forceFill(['consumed_at' => now()])->save();

            $isCentre = $pending->role === 'childcare_centre';
            $personName = $pending->name ?: trim($pending->first_name.' '.$pending->last_name);
            [$splitFirst, $splitLast] = PersonName::split($personName);

            $user = $this->users->create([
                'name' => $isCentre ? $data['centre_name'] : $personName,
                'first_name' => ($pending->first_name ?: $splitFirst) ?: null,
                'last_name' => ($pending->last_name ?: $splitLast) ?: null,
                'email' => $pending->email,
                'phone' => $data['phone'] ?? null,
                'password' => null,
                'role' => $pending->role,
                'status' => 'active',
                'oauth_provider' => $pending->provider,
                'oauth_provider_id' => $pending->provider_user_id,
                'email_verified_at' => null,
                'last_login_at' => now(),
            ]);

            $this->attachProvider($user, $pending->provider, $pending->provider_user_id, $pending->email);
            $this->assignRole($user, $pending->role);
            $this->createRoleProfile($user, [
                'first_name' => $pending->first_name ?: Str::before($personName, ' '),
                'last_name' => $pending->last_name ?? '',
                'centre_name' => $data['centre_name'] ?? null,
                'contact_person' => $personName,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'],
                'province' => $data['province'],
                'postal_code' => $data['postal_code'],
                'country' => $data['country'],
                'is_student' => $data['is_student'] ?? false,
            ]);

            $pending->forceFill(['user_id' => $user->id])->save();
            $created = true;

            return $user->refresh();
        });

        if ($created) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }

    /**
     * Connects a provider to an already signed-in account (Settings → Sign-in methods).
     */
    public function linkProvider(User $user, string $provider, SocialiteUser $socialUser): SocialAccount
    {
        return $this->attachProvider($user, $provider, (string) $socialUser->getId(), $socialUser->getEmail());
    }

    private function attachProvider(User $user, string $provider, string $providerUserId, ?string $email): SocialAccount
    {
        $existing = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($existing && (int) $existing->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'provider' => 'This '.ucfirst($provider).' account is already connected to another Educator Connect account.',
            ]);
        }

        return SocialAccount::updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            ['provider_user_id' => $providerUserId, 'email' => $email],
        );
    }

    public function unlinkProvider(User $user, string $provider): void
    {
        $remaining = $user->socialAccounts()->where('provider', '!=', $provider)->count();
        if (! $user->password && $remaining === 0) {
            throw ValidationException::withMessages([
                'provider' => 'Set a password before disconnecting your only sign-in method.',
            ]);
        }

        $user->socialAccounts()->where('provider', $provider)->delete();
        if ($user->oauth_provider === $provider) {
            $user->forceFill(['oauth_provider' => null, 'oauth_provider_id' => null])->save();
        }
    }

    public function issueToken(User $user, string $deviceName = 'web'): string
    {
        return $user->createToken(Str::limit($deviceName, 100, ''))->plainTextToken;
    }

    public function createOAuthCode(User $user): string
    {
        $plainTextCode = Str::random(64);

        OAuthLoginCode::create([
            'code_hash' => hash('sha256', $plainTextCode),
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(2),
        ]);

        return $plainTextCode;
    }

    public function exchangeOAuthCode(string $plainTextCode): User
    {
        return DB::transaction(function () use ($plainTextCode): User {
            $loginCode = OAuthLoginCode::query()
                ->where('code_hash', hash('sha256', $plainTextCode))
                ->lockForUpdate()
                ->first();

            if (! $loginCode || $loginCode->consumed_at || $loginCode->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'code' => 'This social sign-in code is invalid or expired. Please try again.',
                ]);
            }

            $loginCode->forceFill(['consumed_at' => now()])->save();
            $user = $loginCode->user;

            $this->ensureCanSignIn($user);
            $user->forceFill(['last_login_at' => now()])->save();

            return $user;
        });
    }

    private function assignRole(User $user, string $role): void
    {
        Role::findOrCreate($role, 'web');
        $user->syncRoles([$role]);
    }

    private function createRoleProfile(User $user, array $data): void
    {
        // Professionals have no profile row; they create or claim directory listings later.
        if ($user->role === 'professional') {
            return;
        }

        if ($user->role === 'educator') {
            $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

            $profile = EducatorProfile::create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'] ?? Str::before($name, ' '),
                'last_name' => $data['last_name'] ?? '',
                'slug' => Str::slug($name ?: $user->name).'-'.$user->id,
                'city' => $data['city'] ?? null,
                'province' => $data['province'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? 'Canada',
            ]);

            if ($data['is_student'] ?? false) {
                $profile->studentProfile()->create([
                    'enabled' => true,
                    'looking_for_practicum' => true,
                ]);
            }

            return;
        }

        $centreName = $data['centre_name'] ?? $user->name;
        ChildcareCentre::create([
            'owner_user_id' => $user->id,
            'name' => $centreName,
            'slug' => Str::slug($centreName).'-'.$user->id,
            'contact_person' => $data['contact_person'] ?? null,
            'email' => $user->email,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? '',
            'province' => $data['province'] ?? '',
            'postal_code' => $data['postal_code'] ?? '',
            'country' => $data['country'] ?? 'Canada',
        ]);
    }
}
