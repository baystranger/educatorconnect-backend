<?php

namespace App\Http\Controllers\Api\V1\Educator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Educator\UpdateEducatorProfileRequest;
use App\Http\Requests\MediaUploadRequest;
use App\Http\Resources\EducatorProfileResource;
use App\Services\EducatorProfileService;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(private readonly EducatorProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        return $this->ok(new EducatorProfileResource($this->profiles->profileFor($request->user())));
    }

    public function update(UpdateEducatorProfileRequest $request): JsonResponse
    {
        $profile = $this->profiles->update($request->user(), $request->validated());

        return $this->ok(new EducatorProfileResource($profile), 'Profile saved.');
    }

    public function practicumMode(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $profile = $this->profiles->setPracticumMode($request->user(), $data['enabled']);

        return $this->ok(new EducatorProfileResource($profile), $data['enabled'] ? 'Practicum mode is on.' : 'Practicum mode is off.');
    }

    public function dismissSetup(Request $request): JsonResponse
    {
        $this->profiles->dismissSetup($request->user());

        return $this->ok(null, 'Hidden for now.');
    }

    /** POST /educator/profile/media/{kind} — kind: image (photo) | video | resume */
    public function upload(MediaUploadRequest $request, string $kind): JsonResponse
    {
        $file = $request->file('file');
        $profile = match ($kind) {
            'image' => $this->profiles->uploadPhoto($request->user(), $file),
            'video' => $this->profiles->uploadVideo($request->user(), $file),
            'resume' => $this->profiles->uploadResume($request->user(), $file),
            default => abort(404),
        };

        return $this->ok(new EducatorProfileResource($profile->load(['studentProfile', 'credentials'])), 'Uploaded.');
    }

    public function removeMedia(Request $request, string $kind): JsonResponse
    {
        $profile = match ($kind) {
            'video' => $this->profiles->removeVideo($request->user()),
            'resume' => $this->profiles->removeResume($request->user()),
            default => abort(404),
        };

        return $this->ok(new EducatorProfileResource($profile->load(['studentProfile', 'credentials'])), 'Removed.');
    }

    public function downloadResume(Request $request, MediaStorage $media): StreamedResponse
    {
        $profile = $this->profiles->profileFor($request->user());
        abort_unless($profile->resume_path, 404, 'No résumé uploaded.');

        return Storage::disk($media->privateDisk())->download($profile->resume_path, $profile->resume_original_name ?: 'resume');
    }
}
