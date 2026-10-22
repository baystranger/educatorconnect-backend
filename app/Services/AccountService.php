<?php

namespace App\Services;

use App\Models\User;
use App\Support\ApplicationStages;
use App\Support\PersonName;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountService
{
    private const AUDITED_FIELDS = ['name', 'first_name', 'last_name', 'phone'];

    public function updateDetails(User $user, array $data): User
    {
        $before = $user->only(self::AUDITED_FIELDS);

        DB::transaction(function () use ($user, $data): void {
            if ($user->isEducator() && $user->educatorProfile) {
                // The profile service renames the user too and reloads it, so it runs first.
                app(EducatorProfileService::class)->update($user, Arr::only($data, [
                    'first_name', 'last_name', 'city', 'province', 'postal_code', 'country',
                ]));
            }

            $user->fill(Arr::only($data, ['phone']));
            if (in_array($user->role, ['professional', 'administrator'], true)) {
                // These roles are named by the person: first/last name win, a single `name` is split.
                if (isset($data['first_name']) || isset($data['last_name'])) {
                    $user->first_name = $data['first_name'] ?? $user->first_name;
                    $user->last_name = $data['last_name'] ?? $user->last_name;
                    $user->name = PersonName::join($user->first_name, $user->last_name);
                } elseif (isset($data['name'])) {
                    $user->name = $data['name'];
                    [$user->first_name, $user->last_name] = PersonName::split($data['name']);
                }
            }
            $previousHolder = PersonName::join($user->first_name, $user->last_name);
            $holderRenamed = $user->isCentre() && (isset($data['first_name']) || isset($data['last_name']));
            if ($holderRenamed) {
                // The centre account's `name` is the centre; only the holder's name changes.
                $user->first_name = $data['first_name'] ?? $user->first_name;
                $user->last_name = $data['last_name'] ?? $user->last_name;
            }

            $user->save();

            if ($user->isCentre() && isset($data['contact_person'])) {
                $user->centre?->update(['contact_person' => $data['contact_person']]);
            } elseif ($holderRenamed) {
                app(CentreAccountNameSync::class)->holderRenamed($user, $previousHolder);
            }

        });

        $user->refresh();
        $after = $user->only(self::AUDITED_FIELDS);
        if ($before !== $after) {
            app(AuditTrail::class)->recordChange('account.updated', $user, $before, $after);
        }

        return $user;
    }

    /** Changing the email requires the password (when one is set) and re-verification. */
    public function changeEmail(User $user, string $email, ?string $password): User
    {
        if ($user->password && ! Hash::check((string) $password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your password is incorrect.']);
        }

        $email = Str::lower(trim($email));
        if ($email === $user->email) {
            return $user;
        }

        $previous = $user->email;
        $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();
        app(AuditTrail::class)->record('account.email_changed', $user, ['email' => $previous], ['email' => $email], $user);
        $user->sendEmailVerificationNotification();

        return $user;
    }

    public function changePassword(User $user, ?string $current, string $password, ?string $currentTokenId = null): void
    {
        if ($user->password && ! Hash::check((string) $current, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }

        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        app(AuditTrail::class)->record('account.password_changed', $user, actor: $user);

        // Sign out other API clients.
        $user->tokens()->when($currentTokenId, fn ($q) => $q->whereKeyNot($currentTokenId))->delete();
    }

    /**
     * Hides the profile, withdraws open applications and unpublishes postings. Signing in again
     * within the reactivation window restores the account; afterwards it is purged.
     */
    public function deactivate(User $user, ?string $password): void
    {
        if ($user->password && ! Hash::check((string) $password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'Your password is incorrect.']);
        }

        DB::transaction(function () use ($user): void {
            if ($user->isEducator()) {
                $user->educatorProfile?->update(['open_to_work' => false]);
                foreach ($user->applications()->whereIn('status', ApplicationStages::ACTIVE)->get() as $application) {
                    app(ApplicationService::class)->withdraw($application, $user);
                }
            }
            if ($user->isCentre()) {
                foreach ($user->childcareCentres as $centre) {
                    $centre->jobs()->where('status', 'published')->update(['status' => 'closed', 'closed_at' => now()]);
                    $centre->forceFill(['is_published' => false])->save();
                }
            }
            // Soft-deleted listings leave the directory and come back on reactivation.
            $user->professionalListings()->delete();

            $user->forceFill(['status' => 'deactivated', 'deactivated_at' => now()])->save();
            $user->tokens()->delete();
            app(AuditTrail::class)->record('account.deactivated', $user, ['status' => 'active'], ['status' => 'deactivated'], $user);
        });
    }

    /** A portable copy of the account's own data (Privacy → Download my data). */
    public function export(User $user): array
    {
        $user->load([
            'educatorProfile.credentials', 'educatorProfile.studentProfile',
            'applications.job:id,title,childcare_centre_id', 'applications.job.centre:id,name', 'applications.events',
            'savedJobs.job:id,title', 'childcareCentres.programs', 'childcareCentres.teamMembers', 'childcareCentres.faqs',
            'socialAccounts',
        ]);

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => Arr::only($user->toArray(), ['id', 'name', 'first_name', 'last_name', 'email', 'phone', 'role', 'status', 'email_verified_at', 'created_at']),
            'sign_in_methods' => $user->socialAccounts->map->only(['provider', 'email', 'created_at']),
            'educator_profile' => $user->educatorProfile
                ? Arr::except($user->educatorProfile->toArray(), ['resume_path', 'photo_path', 'introduction_video_path', 'deleted_at'])
                : null,
            'applications' => $user->applications->map(fn ($a) => [
                'job' => $a->job?->title,
                'centre' => $a->job?->centre?->name,
                'status' => $a->status,
                'applied_at' => $a->applied_at,
                'cover_message' => $a->cover_message,
                'history' => $a->events->map->only(['status', 'note', 'created_at']),
            ]),
            'saved_jobs' => $user->savedJobs->map(fn ($s) => $s->job?->title)->filter()->values(),
            'centres' => $user->childcareCentres->map(fn ($c) => Arr::except($c->toArray(), ['licence_number'])),
            'notification_preferences' => app(NotificationPreferences::class)->forUser($user),
        ];
    }
}
