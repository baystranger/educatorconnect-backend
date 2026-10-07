<?php

namespace App\Repositories;

use App\Models\CentreProgram;
use App\Models\ChildcareCentre;
use App\Repositories\Contracts\ChildcareCentreRepositoryInterface;
use App\Support\Geo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ChildcareCentreRepository implements ChildcareCentreRepositoryInterface
{
    public function findForOwner(int $ownerId): ?ChildcareCentre
    {
        return ChildcareCentre::where('owner_user_id', $ownerId)->oldest('id')->first();
    }

    public function findPublicBySlug(string $slug): ?ChildcareCentre
    {
        return ChildcareCentre::query()
            ->where('slug', $slug)
            ->publiclyVisible()
            ->with(['programs', 'gallery', 'teamMembers', 'faqs', 'resources'])
            ->withCount(['gallery', 'jobs as open_roles_count' => fn (Builder $q) => $q->published()])
            ->first();
    }

    /**
     * Filters: keyword, latitude, longitude, radius, location (text fallback), age_group,
     * licence_type, schedule, spaces_available, accepting_waitlist, has_video, verified, sort, per_page.
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = ChildcareCentre::query()->publiclyVisible()->select('childcare_centres.*');

        if (! empty($filters['keyword'])) {
            $keyword = '%'.$filters['keyword'].'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $keyword)->orWhere('about', 'like', $keyword));
        }
        if (! empty($filters['licence_type'])) {
            $query->where('licence_type', $filters['licence_type']);
        }
        if (! empty($filters['verified'])) {
            $query->where('is_verified', true);
        }
        if (! empty($filters['has_video'])) {
            $query->whereNotNull('welcome_video_path');
        }

        $ageGroup = config('platform.age_groups.'.($filters['age_group'] ?? ''));
        $programFilter = function (Builder $program) use ($filters, $ageGroup): void {
            $program->where('is_active', true);
            if ($ageGroup) {
                // Program age range overlaps the requested age group.
                $program->where(fn (Builder $q) => $q->whereNull('min_age_months')->orWhere('min_age_months', '<', $ageGroup['max_months']))
                    ->where(fn (Builder $q) => $q->whereNull('max_age_months')->orWhere('max_age_months', '>', $ageGroup['min_months']));
            }
            if (! empty($filters['schedule'])) {
                $program->where(fn (Builder $q) => $q->where('schedule', $filters['schedule'])->orWhere('schedule', 'part_or_full_day'));
            }
        };

        if ($ageGroup || ! empty($filters['schedule'])) {
            $query->whereHas('programs', $programFilter);
        }
        if (! empty($filters['spaces_available'])) {
            $query->whereHas('programs', function (Builder $program) use ($programFilter): void {
                $programFilter($program);
                $program->where('availability', CentreProgram::AVAILABILITY_SPACES);
            });
        }
        if (! empty($filters['accepting_waitlist'])) {
            $query->whereHas('programs', function (Builder $program) use ($programFilter): void {
                $programFilter($program);
                $program->where('availability', CentreProgram::AVAILABILITY_WAITLIST);
            });
        }

        $hasGeo = isset($filters['latitude'], $filters['longitude']);
        if ($hasGeo) {
            $lat = (float) $filters['latitude'];
            $lng = (float) $filters['longitude'];
            Geo::selectDistance($query, $lat, $lng, 'childcare_centres');
            if (! empty($filters['radius'])) {
                Geo::restrictToRadius($query, $lat, $lng, (float) $filters['radius'], 'childcare_centres');
            }
        } elseif (! empty($filters['location'])) {
            $location = trim(preg_replace('/\(.*\)/', '', $filters['location']));
            $city = trim(explode(',', $location)[0]);
            $postalPrefix = strtoupper(substr(preg_replace('/\s+/', '', $location), 0, 3));
            $query->where(fn (Builder $q) => $q->where('city', 'like', '%'.$city.'%')
                ->orWhere('postal_code', 'like', $postalPrefix.'%'));
        }

        match (true) {
            ($filters['sort'] ?? 'closest') === 'availability' => $query->orderByDesc(
                CentreProgram::query()->selectRaw('count(*)')
                    ->whereColumn('centre_programs.childcare_centre_id', 'childcare_centres.id')
                    ->where('availability', CentreProgram::AVAILABILITY_SPACES)
            ),
            $hasGeo => $query->orderBy('distance'),
            default => $query->orderByDesc('is_verified')->orderBy('name'),
        };

        return $query
            ->with(['programs', 'gallery' => fn ($q) => $q->where('is_cover', true)])
            ->withCount(['gallery' => fn (Builder $q) => $q->where('type', 'image')->where('is_public', true)])
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function create(array $data): ChildcareCentre
    {
        return ChildcareCentre::create($data);
    }

    public function update(ChildcareCentre $centre, array $data): ChildcareCentre
    {
        $centre->update($data);

        return $centre->refresh();
    }
}
