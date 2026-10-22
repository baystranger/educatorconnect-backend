<?php

namespace App\Services;

use App\Models\EducatorCredential;
use App\Models\EducatorProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EducatorProfileService
{
    private const PROFILE_FIELDS = [
        'first_name', 'last_name', 'professional_title', 'website', 'years_experience', 'languages',
        'professional_summary', 'teaching_philosophy', 'age_groups_preferred', 'areas_of_expertise',
        'special_interests', 'professional_development', 'first_aid_cpr', 'criminal_record_check_status',
        'criminal_record_checked_on', 'show_criminal_record_status', 'portfolio', 'availability',
        'available_from', 'search_radius_km', 'employment_preferences', 'open_to_relocation',
        'city', 'province', 'postal_code', 'country', 'open_to_work', 'visibility',
    ];

    private const STUDENT_FIELDS = [
        'enabled', 'college_university', 'program', 'expected_graduation_date', 'practicum_level',
        'practicum_start_date', 'practicum_end_date', 'required_practicum_hours', 'preferred_location',
        'looking_for_practicum',
    ];

    public function __construct(
        private readonly LocationService $locations,
        private readonly MediaStorage $media,
    ) {}

    public function profileFor(User $user): EducatorProfile
    {
        return $user->educatorProfile()->firstOrFail()->load(['studentProfile', 'credentials', 'user']);
    }

    public function update(User $user, array $data): EducatorProfile
    {
        $profile = $this->profileFor($user);

        DB::transaction(function () use ($profile, $user, $data): void {
            $attributes = Arr::only($data, self::PROFILE_FIELDS);
            if (array_key_exists('professional_development', $attributes) && is_array($attributes['professional_development'])) {
                $attributes['professional_development'] = implode("\n", array_filter($attributes['professional_development']));
            }

            $locationChanged = collect(['city', 'province', 'postal_code', 'country'])
                ->contains(fn ($field) => array_key_exists($field, $attributes) && $attributes[$field] !== $profile->{$field});
            if ($locationChanged) {
                $geo = $this->locations->geocode(implode(', ', array_filter([
                    $attributes['postal_code'] ?? $profile->postal_code,
                    $attributes['city'] ?? $profile->city,
                    $attributes['province'] ?? $profile->province,
                    $attributes['country'] ?? $profile->country,
                ])));
                $attributes['latitude'] = $geo['latitude'] ?? null;
                $attributes['longitude'] = $geo['longitude'] ?? null;
            }

            $profile->update($attributes);

            if (isset($attributes['first_name']) || isset($attributes['last_name'])) {
                $user->forceFill([
                    'name' => $profile->fullName(),
                    'first_name' => $profile->first_name,
                    'last_name' => $profile->last_name,
                ])->save();
            }

            if (isset($data['student']) && is_array($data['student'])) {
                $profile->studentProfile()->updateOrCreate([], Arr::only($data['student'], self::STUDENT_FIELDS));
            }
        });

        return $this->profileFor($user->refresh());
    }

    public function setPracticumMode(User $user, bool $enabled): EducatorProfile
    {
        $profile = $this->profileFor($user);
        $profile->studentProfile()->updateOrCreate([], [
            'enabled' => $enabled ? true : ($profile->studentProfile?->enabled ?? false),
            'looking_for_practicum' => $enabled,
        ]);

        return $this->profileFor($user);
    }

    public function dismissSetup(User $user): void
    {
        $user->educatorProfile()->update(['setup_dismissed_at' => now()]);
    }

    public function uploadPhoto(User $user, UploadedFile $file): EducatorProfile
    {
        $profile = $this->profileFor($user);
        $old = $profile->photo_path;
        $profile->update(['photo_path' => $this->media->storePublic($file, "educators/{$profile->id}/photo")]);
        $this->media->deletePublic($old);

        return $profile->refresh();
    }

    public function uploadVideo(User $user, UploadedFile $file): EducatorProfile
    {
        $profile = $this->profileFor($user);
        $old = $profile->introduction_video_path;
        $profile->update(['introduction_video_path' => $this->media->storePublic($file, "educators/{$profile->id}/video")]);
        $this->media->deletePublic($old);

        return $profile->refresh();
    }

    public function removeVideo(User $user): EducatorProfile
    {
        $profile = $this->profileFor($user);
        $this->media->deletePublic($profile->introduction_video_path);
        $profile->update(['introduction_video_path' => null]);

        return $profile->refresh();
    }

    public function uploadResume(User $user, UploadedFile $file): EducatorProfile
    {
        $profile = $this->profileFor($user);
        $old = $profile->resume_path;
        $profile->update([
            'resume_path' => $this->media->storePrivate($file, "educators/{$profile->id}/resume"),
            'resume_original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'resume_size' => $file->getSize(),
        ]);
        $this->media->deletePrivate($old);

        return $profile->refresh();
    }

    public function removeResume(User $user): EducatorProfile
    {
        $profile = $this->profileFor($user);
        $this->media->deletePrivate($profile->resume_path);
        $profile->update(['resume_path' => null, 'resume_original_name' => null, 'resume_size' => null]);

        return $profile->refresh();
    }

    public function addCredential(User $user, array $data): EducatorCredential
    {
        $profile = $this->profileFor($user);

        return $profile->credentials()->create($data + ['sort_order' => $profile->credentials()->count()]);
    }

    public function updateCredential(User $user, int $credentialId, array $data): EducatorCredential
    {
        $credential = $this->profileFor($user)->credentials()->findOrFail($credentialId);
        // Editing a verified credential sends it back for verification.
        $credential->fill($data);
        if ($credential->isDirty(['title', 'issuer', 'expires_on']) && $credential->verification_status === 'verified') {
            $credential->verification_status = 'pending';
        }
        $credential->save();

        return $credential;
    }

    public function deleteCredential(User $user, int $credentialId): void
    {
        $this->profileFor($user)->credentials()->findOrFail($credentialId)->delete();
    }

    /**
     * Profile strength checklist shown on the dashboard and profile page.
     */
    public function completion(EducatorProfile $profile): array
    {
        $profile->loadMissing('credentials');
        $items = [
            'basic' => ['label' => 'Basic information', 'weight' => 20, 'done' => filled($profile->first_name) && filled($profile->city) && filled($profile->professional_title)],
            'certifications' => ['label' => 'Certifications', 'weight' => 20, 'done' => $profile->credentials->isNotEmpty()],
            'about' => ['label' => 'About me', 'weight' => 15, 'done' => filled($profile->professional_summary)],
            'preferences' => ['label' => 'Employment preferences', 'weight' => 15, 'done' => ! empty($profile->employment_preferences)],
            'video' => ['label' => 'Introduction video', 'weight' => 10, 'done' => filled($profile->introduction_video_path), 'hint' => '30–60 seconds', 'todo' => 'Add an intro video'],
            'resume' => ['label' => 'Résumé', 'weight' => 10, 'done' => filled($profile->resume_path), 'hint' => 'PDF or Word', 'todo' => 'Upload your résumé'],
            'philosophy' => ['label' => 'Teaching philosophy', 'weight' => 10, 'done' => filled($profile->teaching_philosophy), 'hint' => 'A few sentences', 'todo' => 'Teaching philosophy'],
        ];

        $percent = collect($items)->sum(fn ($item) => $item['done'] ? $item['weight'] : 0);

        return [
            'percent' => $percent,
            'items' => collect($items)->map(fn ($item, $key) => [
                'key' => $key,
                'label' => $item['label'],
                'done' => $item['done'],
                'todo' => $item['todo'] ?? $item['label'],
                'hint' => $item['hint'] ?? null,
            ])->values()->all(),
        ];
    }
}
