<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\CentreSearchRequest;
use App\Http\Resources\CentreCardResource;
use App\Http\Resources\CentreProfileResource;
use App\Http\Resources\JobResource;
use App\Repositories\Contracts\ChildcareCentreRepositoryInterface;
use App\Services\LocationService;
use App\Services\ProfileViewRecorder;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public childcare search and centre profiles (parents need no account).
 */
class ChildcareCentreController extends Controller
{
    public function __construct(
        private readonly ChildcareCentreRepositoryInterface $centres,
        private readonly LocationService $locations,
        private readonly ProfileViewRecorder $views,
    ) {}

    public function index(CentreSearchRequest $request): JsonResponse
    {
        $filters = $request->validated();
        if (! empty($filters['location']) && ! isset($filters['latitude'])) {
            $geo = $this->locations->geocode($filters['location'].', Canada');
            if ($geo) {
                $filters['latitude'] = $geo['latitude'];
                $filters['longitude'] = $geo['longitude'];
            }
        }

        $results = $this->centres->search($filters);

        return $this->ok(CentreCardResource::collection($results), meta: [
            'origin' => isset($filters['latitude'])
                ? ['latitude' => (float) $filters['latitude'], 'longitude' => (float) $filters['longitude']]
                : null,
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $centre = $this->centres->findPublicBySlug($slug);
        abort_if(! $centre, 404, 'This centre profile is not available.');

        $this->views->record($centre, $request->user('sanctum'), $request->ip().'|'.$request->userAgent());

        if ($request->filled(['latitude', 'longitude']) && $centre->latitude !== null) {
            $centre->distance = Geo::distanceKm((float) $request->query('latitude'), (float) $request->query('longitude'), $centre->latitude, $centre->longitude);
        }

        return $this->ok(new CentreProfileResource($centre));
    }

    public function jobs(string $slug): JsonResponse
    {
        $centre = $this->centres->findPublicBySlug($slug);
        abort_if(! $centre, 404, 'This centre profile is not available.');

        $jobs = $centre->jobs()->published()->latest('published_at')->get()->each->setRelation('centre', $centre);

        return $this->ok(JobResource::collection($jobs));
    }
}
