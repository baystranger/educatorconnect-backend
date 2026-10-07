<?php

namespace App\Http\Controllers\Api\V1\Centre;

use App\Http\Concerns\ResolvesCentre;
use App\Http\Controllers\Controller;
use App\Http\Requests\Centre\CentreSectionRequest;
use App\Http\Requests\MediaUploadRequest;
use App\Http\Resources\CentreOwnerResource;
use App\Services\CentreProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The centre profile builder (steps: basics, about, programs, media, team, parents, review).
 */
class ProfileController extends Controller
{
    use ResolvesCentre;

    public function __construct(private readonly CentreProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        return $this->ok(new CentreOwnerResource($this->profiles->load($this->centre($request))));
    }

    public function updateSection(CentreSectionRequest $request, string $section): JsonResponse
    {
        $centre = $this->centre($request);
        $this->authorize('update', $centre);

        return $this->ok(new CentreOwnerResource($this->profiles->updateSection($centre, $section, $request->validated())), 'All changes saved.');
    }

    /** POST /centre/profile/media/{kind} — kind: image (logo) | video (welcome video) */
    public function upload(MediaUploadRequest $request, string $kind): JsonResponse
    {
        $centre = $this->centre($request);
        $this->authorize('update', $centre);

        match ($kind) {
            'image' => $this->profiles->uploadLogo($centre, $request->file('file')),
            'video' => $this->profiles->uploadWelcomeVideo($centre, $request->file('file'), $request->validated('title')),
            default => abort(404),
        };

        return $this->ok(new CentreOwnerResource($this->profiles->load($centre->refresh())), 'Uploaded.');
    }

    public function removeVideo(Request $request): JsonResponse
    {
        $centre = $this->centre($request);
        $this->authorize('update', $centre);
        $this->profiles->removeWelcomeVideo($centre);

        return $this->ok(new CentreOwnerResource($this->profiles->load($centre->refresh())), 'Welcome video removed.');
    }

    public function teamPhoto(MediaUploadRequest $request, int $member): JsonResponse
    {
        $centre = $this->centre($request);
        $this->authorize('update', $centre);
        $this->profiles->uploadTeamPhoto($centre, $member, $request->file('file'));

        return $this->ok(new CentreOwnerResource($this->profiles->load($centre->refresh())), 'Photo uploaded.');
    }

    public function submit(Request $request): JsonResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Please confirm the information is accurate and you have consent for photos and videos.']);
        $centre = $this->centre($request);
        $this->authorize('update', $centre);

        $centre = $this->profiles->submit($centre);
        $message = $centre->isPubliclyVisible()
            ? 'Your profile is live. Families can now find you.'
            : 'Submitted for review. We’ll email you when it’s live.';

        return $this->ok(new CentreOwnerResource($this->profiles->load($centre)), $message);
    }
}
