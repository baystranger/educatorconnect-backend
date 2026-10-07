<?php

namespace App\Services;

use App\Models\ProfileViewEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Records profile views for analytics (dashboard counts and weekly charts).
 * Repeat views from the same visitor on the same day are counted once.
 */
class ProfileViewRecorder
{
    public function record(Model $viewable, ?User $viewer, string $fingerprint): void
    {
        if ($viewer && $this->isOwner($viewable, $viewer)) {
            return;
        }

        $today = now()->toDateString();
        $key = sprintf('view:%s:%s:%s:%s', class_basename($viewable), $viewable->getKey(), $viewer?->id ?? sha1($fingerprint), $today);
        if (! Cache::add($key, true, now()->endOfDay())) {
            return;
        }

        ProfileViewEvent::create([
            'viewable_type' => $viewable->getMorphClass(),
            'viewable_id' => $viewable->getKey(),
            'viewer_user_id' => $viewer?->id,
            'viewed_on' => $today,
        ]);

        if (array_key_exists('profile_views', $viewable->getAttributes())) {
            $viewable->newQuery()->whereKey($viewable->getKey())->increment('profile_views');
        }
    }

    public function countSince(Model $viewable, CarbonImmutable $since): int
    {
        return $viewable->viewEvents()->where('viewed_on', '>=', $since->toDateString())->count();
    }

    /**
     * @return array<int, array{week_start: string, views: int}> oldest first
     */
    public function weekly(Model $viewable, int $weeks = 8): array
    {
        $start = CarbonImmutable::now()->startOfWeek()->subWeeks($weeks - 1);
        $rows = $viewable->viewEvents()
            ->where('viewed_on', '>=', $start->toDateString())
            ->pluck('viewed_on');

        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $buckets[$start->addWeeks($i)->toDateString()] = 0;
        }
        foreach ($rows as $date) {
            $week = CarbonImmutable::parse($date)->startOfWeek()->toDateString();
            if (isset($buckets[$week])) {
                $buckets[$week]++;
            }
        }

        return collect($buckets)->map(fn ($views, $week) => ['week_start' => $week, 'views' => $views])->values()->all();
    }

    private function isOwner(Model $viewable, User $viewer): bool
    {
        return ($viewable->owner_user_id ?? null) === $viewer->id || ($viewable->user_id ?? null) === $viewer->id;
    }
}
