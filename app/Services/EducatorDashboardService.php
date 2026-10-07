<?php

namespace App\Services;

use App\Http\Resources\EducatorApplicationResource;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Models\User;
use App\Support\ApplicationStages;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EducatorDashboardService
{
    public function __construct(
        private readonly EducatorProfileService $profiles,
        private readonly ProfileViewRecorder $views,
    ) {}

    public function build(User $user, Request $request): array
    {
        $profile = $this->profiles->profileFor($user);
        $completion = $this->profiles->completion($profile);

        $applications = $user->applications()->with('job.centre')->latest('applied_at')->get();
        $active = $applications->whereIn('status', ApplicationStages::ACTIVE);
        $upcomingInterviews = $applications
            ->where('status', 'interview')
            ->filter(fn ($a) => $a->interview_at && $a->interview_at->isFuture())
            ->sortBy('interview_at');
        $nextInterview = $upcomingInterviews->first();

        $saved = $user->savedJobs()->with('job')->get()->filter(fn ($s) => $s->job && $s->job->status === 'published');
        $closingSoon = $saved->filter(fn ($s) => $s->job->application_deadline
            && $s->job->application_deadline->between(now()->startOfDay(), now()->addDays(7)))->count();

        $student = $profile->studentProfile;
        $preferences = $profile->employment_preferences ?? [];

        return [
            'first_name' => $profile->first_name,
            'completion' => $completion,
            'show_setup' => ! $profile->setup_dismissed_at && $completion['percent'] < 100,
            'stats' => [
                'active_applications' => $active->count(),
                'new_this_week' => $applications->filter(fn ($a) => $a->applied_at?->greaterThan(now()->subWeek()))->count(),
                'interviews' => $upcomingInterviews->count(),
                'next_interview_at' => $nextInterview?->interview_at?->toIso8601String(),
                'saved_jobs' => $saved->count(),
                'closing_soon' => $closingSoon,
                'profile_views_30d' => $this->views->countSince($profile, CarbonImmutable::now()->subDays(30)),
            ],
            'applications' => EducatorApplicationResource::collection($applications->take(4))->resolve($request),
            'next_up' => $nextInterview ? [
                'application_id' => $nextInterview->id,
                'at' => $nextInterview->interview_at->toIso8601String(),
                'centre' => $nextInterview->job->centre->name,
                'job_title' => $nextInterview->job->title,
                'location' => $nextInterview->interview_location,
                'details' => $nextInterview->interview_details,
            ] : null,
            'recommended' => JobResource::collection($this->recommended($user, $profile, 3))->resolve($request),
            'recommendation_basis' => [
                'radius_km' => $profile->search_radius_km,
                'postal_prefix' => $profile->postal_code ? strtoupper(substr(str_replace(' ', '', $profile->postal_code), 0, 3)) : null,
                'employment_types' => array_values(array_intersect_key(config('platform.employment_types'), array_flip($preferences))),
            ],
            'practicum' => [
                'is_student' => (bool) $student?->enabled,
                'enabled' => (bool) $student?->looking_for_practicum,
            ],
            'activity' => $user->notifications()->latest()->limit(4)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'href' => $n->data['href'] ?? null,
                'tone' => $n->data['tone'] ?? 'purple',
                'at' => $n->created_at->toIso8601String(),
            ])->values(),
        ];
    }

    /**
     * Published jobs the educator has not applied to, preferring their employment
     * preferences and search radius.
     */
    public function recommended(User $user, $profile, int $limit)
    {
        $base = Job::query()->select('user_jobs.*')->published()
            ->whereDoesntHave('applications', fn (Builder $q) => $q->where('educator_id', $user->id))
            ->where(fn (Builder $q) => $q->whereNull('application_deadline')->orWhere('application_deadline', '>=', now()->toDateString()))
            ->with('centre:id,name,slug,city,logo_path,is_verified,owner_user_id')
            ->withExists(['savedBy as is_saved' => fn (Builder $q) => $q->where('educator_id', $user->id)]);

        if (! ($profile->studentProfile?->looking_for_practicum)) {
            $base->where('employment_type', '!=', 'practicum');
        }

        $hasGeo = $profile->latitude !== null && $profile->longitude !== null;
        if ($hasGeo) {
            Geo::selectDistance($base, $profile->latitude, $profile->longitude, 'user_jobs');
        }

        $preferred = (clone $base);
        if (! empty($profile->employment_preferences)) {
            $preferred->whereIn('employment_type', $profile->employment_preferences);
        }
        if ($hasGeo && $profile->search_radius_km) {
            Geo::restrictToRadius($preferred, $profile->latitude, $profile->longitude, $profile->search_radius_km, 'user_jobs');
        }
        $hasGeo ? $preferred->orderBy('distance') : $preferred->latest('published_at');

        $jobs = $preferred->limit($limit)->get();
        if ($jobs->count() < $limit) {
            $fallback = (clone $base)->whereNotIn('user_jobs.id', $jobs->pluck('id'))->latest('published_at')->limit($limit - $jobs->count())->get();
            $jobs = $jobs->concat($fallback);
        }

        return $jobs;
    }
}
