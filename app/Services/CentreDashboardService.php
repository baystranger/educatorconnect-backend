<?php

namespace App\Services;

use App\Http\Resources\CentreJobResource;
use App\Models\ChildcareCentre;
use App\Models\JobApplication;
use App\Models\ParentRequest;
use App\Support\Format;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CentreDashboardService
{
    public function __construct(
        private readonly CentreProfileService $profiles,
        private readonly ProfileViewRecorder $views,
    ) {}

    public function build(ChildcareCentre $centre, Request $request): array
    {
        $this->profiles->load($centre);
        $completion = $this->profiles->completion($centre);
        $now = CarbonImmutable::now();

        $jobIds = $centre->jobs()->pluck('id');
        $applications = JobApplication::query()->whereIn('job_id', $jobIds);

        $newApplicants = (clone $applications)->where('status', 'new');
        $requests = $centre->parentRequests();

        $views30 = $this->views->countSince($centre, $now->subDays(30));
        $viewsPrev = $centre->viewEvents()->whereBetween('viewed_on', [$now->subDays(60)->toDateString(), $now->subDays(31)->toDateString()])->count();

        $attention = $this->attention($centre, $jobIds->all());
        $nextRecommendation = collect($completion['steps'])->first(fn ($step) => ! $step['complete'] && $step['recommendation']);

        return [
            'name' => $centre->name,
            'slug' => $centre->slug,
            'waiting_count' => $attention['counts']['all'],
            'profile' => [
                'status' => $centre->status,
                'is_published' => (bool) $centre->is_published,
                'is_verified' => (bool) $centre->is_verified,
                'percent' => $completion['percent'],
                'recommendation' => $nextRecommendation['recommendation'] ?? null,
            ],
            'stats' => [
                'open_positions' => $centre->jobs()->published()->count(),
                'drafts' => $centre->jobs()->where('status', 'draft')->count(),
                'new_applicants' => (clone $newApplicants)->count(),
                'new_applicants_this_week' => (clone $applications)->where('applied_at', '>=', $now->subWeek())->count(),
                'inquiries' => (clone $requests)->where('type', 'inquiry')->where('created_at', '>=', $now->subDays(30))->count(),
                'unanswered_inquiries' => (clone $requests)->where('type', 'inquiry')->where('status', 'new')->count(),
                'profile_views_30d' => $views30,
                'profile_views_change' => $viewsPrev > 0 ? (int) round(($views30 - $viewsPrev) / $viewsPrev * 100) : null,
            ],
            'attention' => $attention,
            'this_week' => $this->thisWeek($centre, $jobIds->all(), $now),
            'jobs' => CentreJobResource::collection(
                $centre->jobs()
                    ->whereIn('status', ['published', 'draft'])
                    ->withCount(['applications', 'applications as new_applications_count' => fn (Builder $q) => $q->where('status', 'new')])
                    ->orderByRaw("CASE status WHEN 'published' THEN 0 ELSE 1 END")
                    ->latest('published_at')
                    ->limit(4)
                    ->get()
                    ->each->setRelation('centre', $centre)
            )->resolve($request),
            'views_weekly' => $this->views->weekly($centre, 8),
        ];
    }

    private function attention(ChildcareCentre $centre, array $jobIds): array
    {
        $applicants = JobApplication::query()
            ->whereIn('job_id', $jobIds)
            ->where('status', 'new')
            ->with(['job:id,title,employment_type,practicum_level', 'educator.educatorProfile.credentials', 'educator.educatorProfile.studentProfile'])
            ->latest('applied_at')
            ->get();

        $requests = $centre->parentRequests()->where('status', 'new')->latest()->get();

        $items = collect();
        foreach ($applicants as $application) {
            $profile = $application->educator->educatorProfile;
            $isPracticum = $application->job->employment_type === 'practicum';
            $distance = ($profile?->latitude && $centre->latitude)
                ? Format::distance(Geo::distanceKm($centre->latitude, $centre->longitude, $profile->latitude, $profile->longitude))
                : null;
            $student = $profile?->studentProfile;

            $items->push([
                'kind' => $isPracticum ? 'practicum' : 'applicant',
                'group' => 'hiring',
                'initials' => Format::initials($application->educator->name),
                'title' => $isPracticum
                    ? $application->educator->name.' — Practicum application'.($application->job->practicum_level ? ' ('.config('platform.practicum_levels.'.$application->job->practicum_level).')' : '')
                    : $application->educator->name.' applied — '.$application->job->title,
                'subtitle' => $isPracticum
                    ? implode(' · ', array_filter([
                        $student?->college_university,
                        $student?->practicum_start_date ? $student->practicum_start_date->format('M j').($student->practicum_end_date ? ' – '.$student->practicum_end_date->format('M j') : '') : null,
                        $student?->required_practicum_hours ? $student->required_practicum_hours.' hrs' : null,
                    ]))
                    : implode(' · ', array_filter([
                        $profile?->years_experience ? $profile->years_experience.' yrs experience' : null,
                        $profile?->credentials->where('kind', 'certification')->first()?->title,
                        $distance !== null ? $distance.' km away' : null,
                    ])),
                'badge' => $isPracticum ? 'Practicum' : 'New applicant',
                'action' => 'Review',
                'href' => "/centre/applicants?job={$application->job_id}&application={$application->id}",
                'at' => $application->applied_at?->toIso8601String(),
            ]);
        }

        foreach ($requests as $request) {
            $items->push([
                'kind' => $request->type,
                'group' => 'families',
                'initials' => Format::initials($request->parent_name),
                'title' => match ($request->type) {
                    'tour' => 'Tour request — '.$request->parent_name,
                    'waitlist' => 'Waitlist request — '.($request->program_of_interest ?: 'any program').($request->desired_start ? ', start '.$request->desired_start : ''),
                    default => 'Inquiry — '.Str::limit($request->message ?: 'general question', 40),
                },
                'subtitle' => match ($request->type) {
                    'tour' => implode(' · ', array_filter([
                        $request->child_age ? 'child '.$request->child_age : null,
                        $request->program_of_interest ? $request->program_of_interest.' program' : null,
                    ])),
                    'waitlist' => implode(' · ', array_filter([$request->parent_name, $request->child_age ? 'child '.$request->child_age : null])),
                    default => implode(' · ', array_filter([
                        $request->parent_name,
                        $request->preferred_contact_method ? 'prefers '.strtolower(config('platform.contact_methods.'.$request->preferred_contact_method, $request->preferred_contact_method)) : null,
                        'waiting '.$request->created_at->diffForHumans(null, true),
                    ])),
                },
                // Formatted by the client in the viewer's timezone, like every other time in the app.
                'when' => $request->type === 'tour' ? $request->requested_at?->toIso8601String() : null,
                'badge' => match ($request->type) { 'tour' => 'Tour', 'waitlist' => 'Waitlist', default => 'Unanswered' },
                'action' => match ($request->type) { 'tour' => 'Confirm', 'waitlist' => 'Add', default => 'Reply' },
                'href' => '/centre/families?tab='.$request->type.'&request='.$request->id,
                'at' => $request->created_at->toIso8601String(),
            ]);
        }

        $sorted = $items->sortByDesc('at')->values();

        return [
            'counts' => [
                'all' => $sorted->count(),
                'hiring' => $sorted->where('group', 'hiring')->count(),
                'families' => $sorted->where('group', 'families')->count(),
            ],
            'items' => $sorted->take(8)->all(),
        ];
    }

    private function thisWeek(ChildcareCentre $centre, array $jobIds, CarbonImmutable $now): array
    {
        $from = $now->startOfDay();
        $to = $now->addDays(7)->endOfDay();

        $interviews = JobApplication::query()
            ->whereIn('job_id', $jobIds)
            ->where('status', 'interview')
            ->whereBetween('interview_at', [$from, $to])
            ->with('educator:id,name', 'job:id,title,employment_type')
            ->get()
            ->map(fn ($a) => [
                'kind' => 'interview',
                'title' => 'Interview · '.$a->educator->name,
                'subtitle' => $a->job->employment_type === 'practicum' ? 'Practicum' : $a->job->title,
                'at' => $a->interview_at->toIso8601String(),
                'href' => "/centre/applicants?job={$a->job_id}&application={$a->id}",
            ]);

        $tours = $centre->parentRequests()
            ->where('type', 'tour')
            ->whereIn('status', ['new', 'confirmed', 'replied'])
            ->whereBetween('requested_at', [$from, $to])
            ->get()
            ->map(fn (ParentRequest $r) => [
                'kind' => 'tour',
                'title' => 'Tour · '.$r->parent_name,
                'subtitle' => $r->program_of_interest ? $r->program_of_interest.' room' : 'Centre tour',
                'at' => $r->requested_at->toIso8601String(),
                'href' => '/centre/families?tab=tour&request='.$r->id,
            ]);

        return $interviews->concat($tours)->sortBy('at')->values()->take(6)->all();
    }
}
