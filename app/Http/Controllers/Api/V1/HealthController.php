<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * GET /api/v1/health — a deployment self-check for shared hosting: database reachable,
 * migrations applied, upload folders writable, e-mail queue draining. 503 when anything
 * that breaks the site is wrong. Reports counts only, never configuration or secrets.
 */
class HealthController extends Controller
{
    public function __invoke(MediaStorage $media): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1') !== []),
            'migrations' => $this->check(fn () => $this->pendingMigrations() === 0, fn () => ['pending' => $this->pendingMigrations()]),
            'public_uploads' => $this->check(fn () => $this->writable($media->publicDisk())),
            'private_uploads' => $this->check(fn () => $this->writable($media->privateDisk())),
            // Waiting e-mails older than 10 minutes mean the cron (`schedule:run`) is not running.
            'queue' => $this->check(
                fn () => ! Schema::hasTable('jobs') || DB::table('jobs')->where('available_at', '<', now()->subMinutes(10)->getTimestamp())->doesntExist(),
                fn () => [
                    'waiting' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                    'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                ],
            ),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['ok']);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function check(callable $passes, ?callable $details = null): array
    {
        try {
            return ['ok' => (bool) $passes()] + ($details ? $details() : []);
        } catch (Throwable) {
            return ['ok' => false];
        }
    }

    private function pendingMigrations(): int
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return count($migrator->getMigrationFiles(database_path('migrations')));
        }

        $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));

        return count(array_diff($files, $migrator->getRepository()->getRan()));
    }

    private function writable(string $disk): bool
    {
        $probe = '.health-'.bin2hex(random_bytes(4));
        $written = Storage::disk($disk)->put($probe, 'ok');
        Storage::disk($disk)->delete($probe);

        return (bool) $written;
    }
}
