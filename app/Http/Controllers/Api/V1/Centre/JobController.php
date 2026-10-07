<?php

namespace App\Http\Controllers\Api\V1\Centre;

use App\Http\Concerns\ResolvesCentre;
use App\Http\Controllers\Controller;
use App\Http\Requests\Centre\JobRequest;
use App\Http\Resources\CentreJobResource;
use App\Http\Resources\JobDetailResource;
use App\Models\Job;
use App\Services\JobService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Job postings" for the signed-in centre.
 */
class JobController extends Controller
{
    use ResolvesCentre;

    private const TABS = ['active', 'published', 'draft', 'closed', 'archived'];

    public function __construct(private readonly JobService $jobs) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(self::TABS)],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $centre = $this->centre($request);
        $tab = $data['tab'] ?? 'active';

        $jobs = $centre->jobs()
            ->when($tab === 'active', fn (Builder $q) => $q->where('status', '!=', 'archived'))
            ->when($tab !== 'active', fn (Builder $q) => $q->where('status', $tab))
            ->when($data['search'] ?? null, fn (Builder $q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->withCount(['applications', 'applications as new_applications_count' => fn (Builder $q) => $q->where('status', 'new')])
            ->orderByRaw("CASE status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 WHEN 'closed' THEN 2 ELSE 3 END")
            ->latest('updated_at')
            ->get()
            ->each->setRelation('centre', $centre);

        $counts = $centre->jobs()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return $this->ok(CentreJobResource::collection($jobs), meta: [
            'counts' => [
                'active' => (int) $counts->except('archived')->sum(),
                'published' => (int) ($counts['published'] ?? 0),
                'draft' => (int) ($counts['draft'] ?? 0),
                'closed' => (int) ($counts['closed'] ?? 0),
                'archived' => (int) ($counts['archived'] ?? 0),
            ],
        ]);
    }

    public function show(Request $request, Job $job): JsonResponse
    {
        $this->authorize('manage', $job);

        return $this->ok(new JobDetailResource($job->load('centre')), meta: [
            'missing_for_publish' => $this->jobs->missingForPublish($job),
        ]);
    }

    public function store(JobRequest $request): JsonResponse
    {
        $centre = $this->centre($request);
        $publish = $request->boolean('publish');
        $job = $this->jobs->create($centre, $request->validated(), $publish);

        return $this->created(new JobDetailResource($job->load('centre')), $publish ? 'Your job is live.' : 'Draft saved.');
    }

    public function update(JobRequest $request, Job $job): JsonResponse
    {
        $this->authorize('update', $job);
        $publish = $request->boolean('publish');
        $job = $this->jobs->update($job->load('centre'), $request->validated(), $publish);

        return $this->ok(new JobDetailResource($job->load('centre')), $publish ? 'Your job is live.' : 'Changes saved.');
    }

    /** POST /centre/jobs/{job}/{action} — publish | close | archive | duplicate */
    public function transition(Request $request, Job $job, string $action): JsonResponse
    {
        $this->authorize('update', $job);

        [$result, $message] = match ($action) {
            'publish' => [$this->jobs->publish($job), 'Your job is live.'],
            'close' => [$this->jobs->close($job), 'Posting closed. Applicants are kept.'],
            'archive' => [$this->jobs->archive($job), 'Posting archived.'],
            'duplicate' => [$this->jobs->duplicate($job), 'Copy saved as a draft.'],
            default => abort(404),
        };

        return $this->ok(new JobDetailResource($result->load('centre')), $message);
    }

    public function destroy(Request $request, Job $job): JsonResponse
    {
        $this->authorize('delete', $job);
        $this->jobs->delete($job);

        return $this->ok(null, 'Draft deleted.');
    }
}
