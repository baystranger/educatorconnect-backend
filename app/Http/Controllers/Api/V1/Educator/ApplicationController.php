<?php

namespace App\Http\Controllers\Api\V1\Educator;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Http\Resources\EducatorApplicationResource;
use App\Http\Resources\MessageResource;
use App\Models\Job;
use App\Models\JobApplication;
use App\Services\ApplicationService;
use App\Services\MessageService;
use App\Support\ApplicationStages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "My jobs" → Applications, plus applying to a job.
 */
class ApplicationController extends Controller
{
    public function __construct(private readonly ApplicationService $applications) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['filter' => ['nullable', Rule::in(['all', 'in_progress', 'interviews', 'offers', 'closed'])]]);
        $base = $request->user()->applications();

        $statuses = ApplicationStages::filterStatuses($data['filter'] ?? 'all');
        $applications = (clone $base)
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->with(['job.centre', 'events', 'messages'])
            ->latest('applied_at')
            ->paginate(20);

        $counts = (clone $base)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return $this->ok(EducatorApplicationResource::collection($applications), meta: [
            'counts' => [
                'all' => (int) $counts->sum(),
                'in_progress' => (int) $counts->only(ApplicationStages::filterStatuses('in_progress'))->sum(),
                'interviews' => (int) $counts->only(['interview'])->sum(),
                'offers' => (int) $counts->only(['offered', 'hired'])->sum(),
                'closed' => (int) $counts->only(ApplicationStages::CLOSED)->sum(),
            ],
            'saved_count' => $request->user()->savedJobs()->count(),
        ]);
    }

    public function show(Request $request, JobApplication $application): JsonResponse
    {
        $this->authorize('own', $application);

        return $this->ok(new EducatorApplicationResource($application->load(['job.centre', 'events', 'messages'])));
    }

    public function store(Request $request, Job $job): JsonResponse
    {
        $data = $request->validate([
            'cover_message' => ['nullable', 'string', 'max:5000'],
            'share_video' => ['sometimes', 'boolean'],
            'share_certifications' => ['sometimes', 'boolean'],
        ]);
        $application = $this->applications->apply($job, $request->user(), $data['cover_message'] ?? null, [
            'video' => $data['share_video'] ?? true,
            'certifications' => $data['share_certifications'] ?? true,
        ]);

        return $this->created(new EducatorApplicationResource($application), 'Application sent to '.$job->centre->name.'.');
    }

    public function withdraw(Request $request, JobApplication $application): JsonResponse
    {
        $this->authorize('own', $application);
        $application = $this->applications->withdraw($application, $request->user());

        return $this->ok(new EducatorApplicationResource($application->load(['job.centre', 'events'])), 'Application withdrawn.');
    }

    public function message(MessageRequest $request, JobApplication $application, MessageService $messages): JsonResponse
    {
        $this->authorize('own', $application);
        abort_if($application->messages()->doesntExist(), 422, 'You can reply once the centre has messaged you.');

        $message = $messages->postToApplication($application->load('job.centre.owner'), $request->user(), $request->validated('body'));

        return $this->created(new MessageResource($message), 'Message sent.');
    }
}
