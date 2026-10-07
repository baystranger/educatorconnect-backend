<?php

namespace App\Providers;

use App\Repositories\ChildcareCentreRepository;
use App\Repositories\Contracts\ChildcareCentreRepositoryInterface;
use App\Repositories\Contracts\JobRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\JobRepository;
use App\Repositories\UserRepository;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\LinkedIn\Provider as LinkedInProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ChildcareCentreRepositoryInterface::class, ChildcareCentreRepository::class);
        $this->app->bind(JobRepositoryInterface::class, JobRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    public function boot(): void
    {
        // Surface N+1 queries and silently-dropped attributes during development.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->registerSqliteMathFunctions();

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('linkedin', LinkedInProvider::class);
        });

        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            return rtrim(config('frontend.url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Public parent forms: generous for a family, tight for bots.
        RateLimiter::for('public-form', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perDay(30)->by($request->ip()),
        ]);

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * SQLite (local development and tests) lacks the trigonometric functions used by
     * the distance search; MySQL/PostgreSQL provide them natively.
     */
    private function registerSqliteMathFunctions(): void
    {
        $register = function ($connection): void {
            if ($connection->getDriverName() !== 'sqlite') {
                return;
            }
            $pdo = $connection->getPdo();
            foreach (['acos', 'cos', 'sin', 'radians' => 'deg2rad'] as $name => $fn) {
                $sqlName = is_int($name) ? $fn : $name;
                $pdo->sqliteCreateFunction($sqlName, fn ($value) => $value === null ? null : $fn((float) $value), 1);
            }
        };

        Event::listen(ConnectionEstablished::class, fn (ConnectionEstablished $event) => $register($event->connection));

        if (config('database.default') === 'sqlite') {
            $this->app->booted(function () use ($register): void {
                rescue(fn () => $register(DB::connection()), report: false);
            });
        }
    }
}
