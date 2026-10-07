<?php

namespace App\Repositories;

use App\Models\Job;
use App\Models\User;
use App\Repositories\Contracts\JobRepositoryInterface;
use App\Support\Geo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class JobRepository implements JobRepositoryInterface
{
    /**
     * Filters: keyword, latitude, longitude, radius, location (text fallback), employment_types[],
     * category, posted_within (days), practicum_only, sort (closest|newest), per_page.
     */
    public function search(array $filters, ?User $viewer = null): LengthAwarePaginator
    {
        $query = $this->filtered($filters, true);
        $this->withViewerFlags($query, $viewer);

        $hasGeo = isset($filters['latitude'], $filters['longitude']);
        if (($filters['sort'] ?? 'closest') === 'closest' && $hasGeo) {
            $query->orderBy('distance');
        } else {
            $query->latest('user_jobs.published_at');
        }

        return $query->with('centre:id,name,slug,city,logo_path,is_verified,owner_user_id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    public function employmentTypeFacets(array $filters): array
    {
        unset($filters['employment_types']);
        $counts = $this->filtered($filters, false)
            ->reorder()
            ->selectRaw('user_jobs.employment_type, count(*) as aggregate')
            ->groupBy('user_jobs.employment_type')
            ->pluck('aggregate', 'employment_type');

        return collect(array_keys(config('platform.employment_types')))
            ->mapWithKeys(fn ($type) => [$type => (int) ($counts[$type] ?? 0)])
            ->all();
    }

    public function findPublic(string $slug, ?User $viewer = null): ?Job
    {
        $query = Job::query()->select('user_jobs.*')->where('slug', $slug)->published()->with('centre');
        $this->withViewerFlags($query, $viewer);

        return $query->first();
    }

    public function create(array $data): Job
    {
        return Job::create($data);
    }

    public function update(Job $job, array $data): Job
    {
        $job->update($data);

        return $job->refresh();
    }

    private function filtered(array $filters, bool $withDistance): Builder
    {
        $query = Job::query()->published()
            ->whereHas('centre', fn (Builder $centre) => $centre->whereNull('deleted_at'));

        if (! empty($filters['keyword'])) {
            $keyword = '%'.$filters['keyword'].'%';
            $query->where(fn (Builder $q) => $q->where('user_jobs.title', 'like', $keyword)
                ->orWhere('user_jobs.description', 'like', $keyword)
                ->orWhereHas('centre', fn (Builder $c) => $c->where('name', 'like', $keyword)));
        }

        if (! empty($filters['employment_types'])) {
            $query->whereIn('user_jobs.employment_type', (array) $filters['employment_types']);
        }
        if (! empty($filters['practicum_only'])) {
            $query->where('user_jobs.employment_type', 'practicum');
        }
        if (! empty($filters['category'])) {
            $query->where('user_jobs.category', $filters['category']);
        }
        if (! empty($filters['posted_within'])) {
            $query->where('user_jobs.published_at', '>=', now()->subDays((int) $filters['posted_within']));
        }

        if (isset($filters['latitude'], $filters['longitude'])) {
            $lat = (float) $filters['latitude'];
            $lng = (float) $filters['longitude'];
            if ($withDistance) {
                Geo::selectDistance($query, $lat, $lng, 'user_jobs');
            }
            if (! empty($filters['radius'])) {
                Geo::restrictToRadius($query, $lat, $lng, (float) $filters['radius'], 'user_jobs');
            }
        } elseif (! empty($filters['location'])) {
            $location = trim($filters['location']);
            $postalPrefix = strtoupper(substr(preg_replace('/\s+/', '', $location), 0, 3));
            $query->where(fn (Builder $q) => $q->where('user_jobs.city', 'like', '%'.$location.'%')
                ->orWhere('user_jobs.postal_code', 'like', $postalPrefix.'%')
                ->orWhere('user_jobs.province', 'like', '%'.$location.'%'));
        }

        if ($withDistance && ! $query->getQuery()->columns) {
            $query->select('user_jobs.*');
        }

        return $query;
    }

    private function withViewerFlags(Builder $query, ?User $viewer): void
    {
        if (! $viewer?->isEducator()) {
            return;
        }

        $query->withExists([
            'savedBy as is_saved' => fn (Builder $q) => $q->where('educator_id', $viewer->id),
            'applications as has_applied' => fn (Builder $q) => $q->where('educator_id', $viewer->id)->where('status', '!=', 'withdrawn'),
        ]);
    }
}
