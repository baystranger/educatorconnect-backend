<?php

use App\Models\ChildcareCentre;
use App\Models\EducatorCredential;
use App\Models\EducatorProfile;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\OAuthLoginCode;
use App\Models\OAuthPendingSignup;
use App\Models\ParentRequest;
use App\Models\ProfessionalClaim;
use App\Models\ProfessionalListing;
use App\Models\User;
use App\Support\PersonName;
use Database\Seeders\DemoMedia;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

Artisan::command('app:create-admin {email} {--name=Administrator}', function (string $email) {
    $password = $this->secret('Password (10+ characters with a number and a symbol)');
    if (! $password || strlen($password) < 10 || ! preg_match('/\d/', $password) || ! preg_match('/[^A-Za-z0-9]/', $password)) {
        $this->error('Password must be at least 10 characters and include a number and a symbol.');

        return 1;
    }

    $user = User::query()->firstOrNew(['email' => Str::lower($email)]);
    [$firstName, $lastName] = PersonName::split($this->option('name'));
    $user->forceFill([
        'name' => $this->option('name'),
        'first_name' => $firstName ?: null,
        'last_name' => $lastName ?: null,
        'password' => Hash::make($password),
        'role' => 'administrator',
        'status' => 'active',
        'email_verified_at' => now(),
    ])->save();

    Role::findOrCreate('administrator', 'web');
    $user->syncRoles(['administrator']);
    $this->info("Administrator {$user->email} is ready.");
})->purpose('Create or promote an administrator account');

Artisan::command('accounts:purge-deactivated', function () {
    $cutoff = now()->subDays((int) config('platform.account.reactivation_window_days', 30));
    $count = 0;

    User::query()
        ->where('status', 'deactivated')
        ->where('deactivated_at', '<', $cutoff)
        ->each(function (User $user) use (&$count): void {
            $user->educatorProfile?->forceDelete();
            $user->childcareCentres()->each(fn ($centre) => $centre->forceDelete());
            $user->tokens()->delete();
            $user->forceDelete();
            $count++;
        });

    $this->info("Purged {$count} deactivated account(s).");
})->purpose('Permanently delete accounts deactivated beyond the reactivation window');

Artisan::command('demo:purge {--force : Skip the confirmation prompt}', function () {
    $domain = config('platform.demo.email_domain');
    $userIds = User::query()->where('email', 'like', '%@'.$domain)->pluck('id');
    $listingIds = ProfessionalListing::withTrashed()->whereIn('slug', DemoSeeder::LISTING_SLUGS)->pluck('id');

    if ($userIds->isEmpty() && $listingIds->isEmpty()) {
        $this->info('No demo data found.');

        return 0;
    }
    if (! $this->option('force') && ! $this->confirm("Delete {$userIds->count()} demo account(s) on @{$domain} and everything they own?")) {
        return 1;
    }

    DB::transaction(function () use ($userIds, $listingIds): void {
        $morph = fn (string $class) => (new $class)->getMorphClass();
        $centreIds = ChildcareCentre::withTrashed()->whereIn('owner_user_id', $userIds)->pluck('id');
        $profileIds = EducatorProfile::withTrashed()->whereIn('user_id', $userIds)->pluck('id');
        $jobIds = Job::withTrashed()->whereIn('childcare_centre_id', $centreIds)->pluck('id');
        $applicationIds = JobApplication::query()->whereIn('educator_id', $userIds)->orWhereIn('job_id', $jobIds)->pluck('id');
        $requestIds = ParentRequest::query()->whereIn('childcare_centre_id', $centreIds)->pluck('id');
        $credentialIds = EducatorCredential::query()->whereIn('educator_profile_id', $profileIds)->pluck('id');
        $claimIds = ProfessionalClaim::query()->whereIn('user_id', $userIds)->orWhereIn('professional_listing_id', $listingIds)->pluck('id');

        // Polymorphic rows have no foreign key, so they are removed explicitly.
        $subjects = [
            $morph(User::class) => $userIds, $morph(ChildcareCentre::class) => $centreIds,
            $morph(EducatorProfile::class) => $profileIds, $morph(JobApplication::class) => $applicationIds,
            $morph(ParentRequest::class) => $requestIds, $morph(EducatorCredential::class) => $credentialIds,
            $morph(ProfessionalClaim::class) => $claimIds, $morph(ProfessionalListing::class) => $listingIds,
        ];
        foreach (['messages' => 'conversation', 'profile_view_events' => 'viewable', 'notifications' => 'notifiable',
            'addresses' => 'addressable', 'audit_logs' => 'auditable', 'personal_access_tokens' => 'tokenable'] as $table => $morphName) {
            foreach ($subjects as $type => $ids) {
                DB::table($table)->where("{$morphName}_type", $type)->whereIn("{$morphName}_id", $ids)->delete();
            }
        }
        DB::table('audit_logs')->whereIn('actor_user_id', $userIds)->delete();
        foreach ([config('permission.table_names.model_has_roles'), config('permission.table_names.model_has_permissions')] as $table) {
            DB::table($table)->where('model_type', $morph(User::class))->whereIn(config('permission.column_names.model_morph_key'), $userIds)->delete();
        }
        DB::table('sessions')->whereIn('user_id', $userIds)->delete();
        DB::table('password_reset_tokens')->where('email', 'like', '%@'.config('platform.demo.email_domain'))->delete();

        // Everything else hangs off these rows by cascading foreign keys.
        ProfessionalListing::withTrashed()->whereIn('id', $listingIds)->forceDelete();
        ChildcareCentre::withTrashed()->whereIn('id', $centreIds)->forceDelete();
        EducatorProfile::withTrashed()->whereIn('id', $profileIds)->forceDelete();
        User::query()->whereIn('id', $userIds)->delete();
    });

    (new DemoMedia(config('platform.uploads.disk', 'public'), config('platform.uploads.private_disk', 'local')))->purge();

    $this->info("Removed {$userIds->count()} demo account(s), {$listingIds->count()} demo listing(s) and the demo media files.");

    return 0;
})->purpose('Remove the demo accounts, their data and demo media loaded by DemoSeeder');

Artisan::command('auth:prune-oauth-codes', function () {
    $deleted = OAuthLoginCode::query()->where('expires_at', '<', now()->subHour())->delete();
    $pending = OAuthPendingSignup::query()->where('expires_at', '<', now()->subHour())->delete();
    $this->info("Pruned {$deleted} expired OAuth code(s) and {$pending} pending sign-up(s).");
})->purpose('Delete expired single-use OAuth login codes and pending social sign-ups');

Schedule::command('accounts:purge-deactivated')->dailyAt('03:00');
Schedule::command('auth:prune-oauth-codes')->hourly();
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Emails and notifications are queued (QUEUE_CONNECTION=database). Shared hosting such as
// cPanel can't keep a long-running worker alive, so the scheduler drains the queue every
// minute: one cron entry (`* * * * * php artisan schedule:run`) is all a server needs.
// With a dedicated worker (Supervisor, `php artisan queue:work`) set QUEUE_VIA_SCHEDULER=false.
if (env('QUEUE_VIA_SCHEDULER', true)) {
    Schedule::command('queue:work --stop-when-empty --tries=3 --backoff=30 --max-time=50')
        ->everyMinute()
        ->withoutOverlapping(5)
        ->runInBackground();
}
