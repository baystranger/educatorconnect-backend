<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\JobSearchRequest;
use App\Http\Resources\JobDetailResource;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Repositories\Contracts\JobRepositoryInterface;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public job search and job detail. Signed-in educators also get saved/applied flags.
 */
class JobController extends Controller
{
    public function __construct(
        private readonly JobRepositoryInterface $jobs,
        private readonly LocationService $locations,
    ) {}

    public function index(JobSearchRequest $request): JsonResponse
    {
        $filters = $this->resolveLocation($request->validated());
        $results = $this->jobs->search($filters, $request->user('sanctum'));

        return $this->ok(JobResource::collection($results), meta: [
            'facets' => ['employment_types' => $this->jobs->employmentTypeFacets($filters)],
            'geo' => isset($filters['latitude']),
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $job = $this->jobs->findPublic($slug, $request->user('sanctum'));
        abort_if(! $job, 404, 'This job is no longer available.');

        Job::query()->whereKey($job->id)->increment('views');

        return $this->ok(new JobDetailResource($job));
    }

    private function resolveLocation(array $filters): array
    {
        if (! empty($filters['location']) && ! isset($filters['latitude'])) {
            $geo = $this->locations->geocode($filters['location'].', Canada');
            if ($geo) {
                $filters['latitude'] = $geo['latitude'];
                $filters['longitude'] = $geo['longitude'];
            }
        }

        return $filters;
    }
}
