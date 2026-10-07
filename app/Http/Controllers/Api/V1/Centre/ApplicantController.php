<?php

namespace App\Http\Controllers\Api\V1\Centre;

use App\Http\Concerns\ResolvesCentre;
use App\Http\Controllers\Controller;
use App\Http\Requests\Centre\ApplicationStatusRequest;
use App\Http\Requests\MessageRequest;
use App\Http\Resources\ApplicantResource;
use App\Http\Resources\MessageResource;
use App\Models\Job;
use App\Models\JobApplication;
use App\Services\ApplicationService;
use App\Services\MediaStorage;
use App\Services\MessageService;
use App\Support\ApplicationStages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Applicants & messages" for a posting.
 */
class ApplicantController extends Controller
{
    use ResolvesCentre;

    private const RELATIONS = ['job.centre', 'educator.educatorProfile.credentials'];

    public function __construct(private readonly ApplicationService $applications) {}

    public function index(Request $request, Job $job): JsonResponse
    {
        $this->authorize('manage', $job);
        $data = $request->validate(['status' => ['nullable', Rule::in(array_merge(ApplicationStages::CENTRE_SETTABLE, ['withdrawn']))]]);

        $applicants = $job->applications()
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->with(self::RELATIONS)
            ->latest('applied_at')
            ->get();

        $counts = $job->applications()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return $this->ok(ApplicantResource::collection($applicants), meta: [
            'job' => ['id' => $job->id, 'title' => $job->title, 'slug' => $job->slug, 'status' => $job->status],
            'counts' => collect(ApplicationStages::CENTRE_SETTABLE)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)]),
        ]);
    }

    public function show(Request $request, JobApplication $application): JsonResponse
    {
        $this->authorize('review', $application);
        $this->applications->markSeen($application);

        return $this->ok((new ApplicantResource($application->load([...self::RELATIONS, 'messages'])))->detailed());
    }

    public function updateStatus(ApplicationStatusRequest $request, JobApplication $application): JsonResponse
    {
        $this->authorize('review', $application);
        $application = $this->applications->changeStatus($application->load('educator'), $request->user(), $request->validated());

        return $this->ok(
            (new ApplicantResource($application->load([...self::RELATIONS, 'messages'])))->detailed(),
            'Moved to '.ApplicationStages::label($application->status).'.',
        );
    }

    public function message(MessageRequest $request, JobApplication $application, MessageService $messages): JsonResponse
    {
        $this->authorize('review', $application);
        $message = $messages->postToApplication($application->load(['educator', 'job.centre']), $request->user(), $request->validated('body'));

        return $this->created(new MessageResource($message), 'Message sent by email and in-app.');
    }

    public function resume(Request $request, JobApplication $application, MediaStorage $media): StreamedResponse
    {
        $this->authorize('review', $application);
        $profile = $application->educator->educatorProfile;
        $path = $application->resume_path ?: $profile?->resume_path;
        abort_unless($path && Storage::disk($media->privateDisk())->exists($path), 404, 'This applicant has not uploaded a résumé.');

        $name = ($profile?->fullName() ?: 'applicant').' - résumé.'.pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk($media->privateDisk())->download($path, $name);
    }
}
