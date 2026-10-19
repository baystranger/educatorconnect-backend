<?php

use App\Models\OAuthLoginCode;
use App\Models\OAuthPendingSignup;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
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
    $user->forceFill([
        'name' => $this->option('name'),
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
