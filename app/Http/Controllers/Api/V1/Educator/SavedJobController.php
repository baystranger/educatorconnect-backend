<?php

namespace App\Http\Controllers\Api\V1\Educator;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Models\SavedJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $jobs = Job::query()
            ->select('user_jobs.*')
            ->join('saved_jobs', 'saved_jobs.job_id', '=', 'user_jobs.id')
            ->where('saved_jobs.educator_id', $user->id)
            ->with('centre:id,name,slug,city,logo_path,is_verified,owner_user_id')
            ->withExists(['applications as has_applied' => fn (Builder $q) => $q->where('educator_id', $user->id)->where('status', '!=', 'withdrawn')])
            ->orderByDesc('saved_jobs.created_at')
            ->paginate(20);

        $jobs->getCollection()->each(fn ($job) => $job->is_saved = true);

        return $this->ok(JobResource::collection($jobs));
    }

    public function store(Request $request, Job $job): JsonResponse
    {
        abort_unless($job->status === 'published', 422, 'This job is no longer available.');
        SavedJob::firstOrCreate(['job_id' => $job->id, 'educator_id' => $request->user()->id]);

        return $this->ok(['job_id' => $job->id, 'saved' => true], 'Job saved.');
    }

    public function destroy(Request $request, Job $job): JsonResponse
    {
        SavedJob::where('job_id', $job->id)->where('educator_id', $request->user()->id)->delete();

        return $this->ok(['job_id' => $job->id, 'saved' => false], 'Removed from saved jobs.');
    }
}
