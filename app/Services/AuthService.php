<?php

namespace App\Services;

use App\Models\ChildcareCentre;
use App\Models\EducatorProfile;
use App\Models\OAuthLoginCode;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
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
            $displayName = $isCentre
                ? $data['centre_name']
                : trim($data['first_name'].' '.$data['last_name']);

            $user = $this->users->create([
                'name' => $displayName,
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

                return;
            }
        }

        if ($user->status !== 'active') {
            throw new AuthorizationException('This account is not active.');
        }
    }

    public function authenticateWithProvider(
        string $provider,
        SocialiteUser $socialUser,
        ?string $role,
        string $intent,
    ): User {
        return DB::transaction(function () use ($provider, $socialUser, $role, $intent): User {
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

            $email = $socialUser->getEmail();
            if (! $email) {
                throw ValidationException::withMessages([
                    'email' => 'The social provider did not return an email address.',
                ]);
            }

            if ($this->users->findByEmail(strtolower($email))) {
                throw ValidationException::withMessages([
                    'email' => 'This email already has an account. Sign in with that account before connecting a social provider.',
                ]);
            }

            $name = trim((string) ($socialUser->getName() ?: $socialUser->getNickname()));
            $name = $name !== '' ? $name : Str::before($email, '@');
            $role ??= 'educator';
            $nameParts = preg_split('/\s+/', $name, 2) ?: [$name];

            $user = $this->users->create([
                'name' => $role === 'childcare_centre' ? $name."'s Childcare Centre" : $name,
                'email' => strtolower($email),
                'password' => null,
                'role' => $role,
                'status' => 'active',
                'oauth_provider' => $provider,
                'oauth_provider_id' => (string) $socialUser->getId(),
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ]);

            $this->linkProvider($user, $provider, $socialUser);
            $this->assignRole($user, $role);
            $this->createRoleProfile($user, [
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
                'centre_name' => $name."'s Childcare Centre",
                'contact_person' => $name,
                'city' => '',
                'province' => '',
                'postal_code' => '',
                'country' => 'Canada',
                'address' => '',
            ]);

            return $user;
        });
    }

    /**
     * Connects a provider to an already signed-in account (Settings → Sign-in methods).
     */
    public function linkProvider(User $user, string $provider, SocialiteUser $socialUser): SocialAccount
    {
        $existing = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', (string) $socialUser->getId())
            ->first();

        if ($existing && (int) $existing->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'provider' => 'This '.ucfirst($provider).' account is already connected to another Educator Connect account.',
            ]);
        }

        return SocialAccount::updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            ['provider_user_id' => (string) $socialUser->getId(), 'email' => $socialUser->getEmail()],
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
