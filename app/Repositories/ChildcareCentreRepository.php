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
                $radius = (float) $filters['radius'];
                $query->where(function (Builder $q) use ($lat, $lng, $radius, $filters): void {
                    $q->where(fn (Builder $inside) => Geo::restrictToRadius($inside, $lat, $lng, $radius, 'childcare_centres'));
                    // Centres without coordinates yet still show when they match the typed place.
                    if (! empty($filters['location'])) {
                        $q->orWhere(fn (Builder $unmapped) => $this->matchLocationText(
                            $unmapped->where(fn (Builder $n) => $n->whereNull('childcare_centres.latitude')->orWhereNull('childcare_centres.longitude')),
                            $filters['location'],
                        ));
                    }
                });
            }
        } elseif (! empty($filters['location'])) {
            $this->matchLocationText($query, $filters['location']);
        }

        match (true) {
            ($filters['sort'] ?? 'closest') === 'availability' => $query->orderByDesc(
                CentreProgram::query()->selectRaw('count(*)')
                    ->whereColumn('centre_programs.childcare_centre_id', 'childcare_centres.id')
                    ->where('availability', CentreProgram::AVAILABILITY_SPACES)
            ),
            // Unmapped centres (NULL distance) go last.
            $hasGeo => $query->orderByRaw('distance IS NULL')->orderBy('distance'),
            default => $query->orderByDesc('is_verified')->orderBy('name'),
        };

        return $query
            ->with(['programs', 'gallery' => fn ($q) => $q->where('is_cover', true)])
            ->withCount(['gallery' => fn (Builder $q) => $q->where('type', 'image')->where('is_public', true)])
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function originForLocation(string $location): ?array
    {
        $row = $this->matchLocationText(ChildcareCentre::query()->publiclyVisible(), $location)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('AVG(latitude) AS lat, AVG(longitude) AS lng, COUNT(*) AS n')
            ->toBase()
            ->first();

        if (! $row || ! (int) $row->n) {
            return null;
        }

        return ['latitude' => (float) $row->lat, 'longitude' => (float) $row->lng];
    }

    /** City name or postal-code prefix match for a typed location ("Surrey", "Surrey, BC", "V3T 0A1"). */
    private function matchLocationText(Builder $query, string $location): Builder
    {
        $location = trim(preg_replace('/\(.*\)/', '', $location));
        $city = trim(explode(',', $location)[0]);
        $postalPrefix = strtoupper(substr(preg_replace('/\s+/', '', $location), 0, 3));

        return $query->where(fn (Builder $q) => $q->where('childcare_centres.city', 'like', '%'.$city.'%')
            ->orWhere('childcare_centres.postal_code', 'like', $postalPrefix.'%'));
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
