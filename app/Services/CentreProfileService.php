<?php

namespace App\Services;

use App\Models\CentreGalleryItem;
use App\Models\ChildcareCentre;
use App\Notifications\CentreProfilePublishedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The centre profile builder: per-step updates, media, completion scoring and publishing.
 */
class CentreProfileService
{
    public const SECTIONS = ['basics', 'about', 'programs', 'media', 'team', 'parents'];

    public function __construct(
        private readonly LocationService $locations,
        private readonly MediaStorage $media,
        private readonly CentreAccountNameSync $nameSync,
    ) {}

    public function load(ChildcareCentre $centre): ChildcareCentre
    {
        return $centre->load(['programs', 'gallery', 'teamMembers', 'faqs', 'resources'])->loadCount('gallery');
    }

    public function updateSection(ChildcareCentre $centre, string $section, array $data): ChildcareCentre
    {
        DB::transaction(function () use ($centre, $section, $data): void {
            match ($section) {
                'basics' => $this->updateBasics($centre, $data),
                'about' => $centre->update(Arr::only($data, ['about', 'philosophy', 'mission_values', 'teaching_approaches', 'daily_schedule', 'highlights'])),
                'programs' => $this->syncChildren($centre->programs(), $data['programs'] ?? [], [
                    'name', 'description', 'min_age_months', 'max_age_months', 'schedule', 'capacity',
                    'educator_count', 'availability', 'tuition_amount', 'tuition_frequency',
                ]),
                'media' => $centre->update(Arr::only($data, ['welcome_video_title', 'welcome_video_captions', 'virtual_tour_url'])),
                'team' => $this->syncChildren($centre->teamMembers(), $data['team'] ?? [], ['name', 'title', 'years_experience', 'credentials', 'bio']),
                'parents' => $this->updateParents($centre, $data),
            };
        });

        return $this->load($centre->refresh());
    }

    private function updateBasics(ChildcareCentre $centre, array $data): void
    {
        $attributes = Arr::only($data, [
            'name', 'contact_person', 'phone', 'email', 'website', 'social_links', 'address', 'city',
            'postal_code', 'province', 'country', 'hours_of_operation', 'licence_type', 'licence_number',
        ]);

        if (isset($attributes['name']) && $attributes['name'] !== $centre->name) {
            $attributes['slug'] = $this->uniqueSlug($attributes['name'], $centre->id);
        }

        $addressChanged = collect(['address', 'city', 'postal_code', 'province', 'country'])
            ->contains(fn ($field) => array_key_exists($field, $attributes) && $attributes[$field] !== $centre->{$field});
        if ($addressChanged || $centre->latitude === null) {
            $geo = $this->locations->geocode(implode(', ', array_filter([
                $attributes['address'] ?? $centre->address,
                $attributes['city'] ?? $centre->city,
                $attributes['province'] ?? $centre->province,
                $attributes['postal_code'] ?? $centre->postal_code,
                $attributes['country'] ?? $centre->country,
            ])));
            if ($geo) {
                $attributes = array_merge($attributes, $geo);
            }
        }

        $centre->update($attributes);

        if ($centre->wasChanged('name')) {
            $this->nameSync->centreRenamed($centre);
        }
    }

    private function updateParents(ChildcareCentre $centre, array $data): void
    {
        $centre->update(Arr::only($data, ['tuition_info', 'tour_info', 'waitlist_info']));
        if (array_key_exists('faqs', $data)) {
            $this->syncChildren($centre->faqs(), $data['faqs'], ['question', 'answer']);
        }
    }

    /**
     * Replaces a has-many collection with the submitted rows, keeping ids that are resubmitted.
     */
    private function syncChildren($relation, array $rows, array $fields): void
    {
        $keep = [];
        foreach (array_values($rows) as $index => $row) {
            $attributes = Arr::only($row, $fields) + ['sort_order' => $index];
            $model = ! empty($row['id']) ? (clone $relation)->whereKey($row['id'])->first() : null;
            if ($model) {
                $model->update($attributes);
            } else {
                $model = $relation->create($attributes);
            }
            $keep[] = $model->id;
        }

        $stale = (clone $relation)->whereNotIn('id', $keep)->get();
        foreach ($stale as $model) {
            $this->media->deletePublic($model->photo_path ?? null);
            $model->delete();
        }
    }

    public function uploadLogo(ChildcareCentre $centre, UploadedFile $file): ChildcareCentre
    {
        $old = $centre->logo_path;
        $centre->update(['logo_path' => $this->media->storePublic($file, "centres/{$centre->id}/logo")]);
        $this->media->deletePublic($old);

        return $centre;
    }

    public function uploadWelcomeVideo(ChildcareCentre $centre, UploadedFile $file, ?string $title): ChildcareCentre
    {
        $old = $centre->welcome_video_path;
        $centre->update([
            'welcome_video_path' => $this->media->storePublic($file, "centres/{$centre->id}/video"),
            'welcome_video_title' => $title ?? $centre->welcome_video_title,
        ]);
        $this->media->deletePublic($old);

        return $centre;
    }

    public function removeWelcomeVideo(ChildcareCentre $centre): ChildcareCentre
    {
        $this->media->deletePublic($centre->welcome_video_path);
        $centre->update(['welcome_video_path' => null]);

        return $centre;
    }

    /**
     * @param  UploadedFile[]  $files
     */
    public function addPhotos(ChildcareCentre $centre, array $files, string $category): array
    {
        $order = (int) $centre->gallery()->max('sort_order');
        $hasCover = $centre->gallery()->where('is_cover', true)->exists();
        $created = [];

        foreach ($files as $file) {
            $created[] = $centre->gallery()->create([
                'type' => 'image',
                'file_path' => $this->media->storePublic($file, "centres/{$centre->id}/gallery"),
                'category' => $category,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'sort_order' => ++$order,
                'is_cover' => ! $hasCover && $created === [],
                'is_public' => true,
            ]);
        }

        return $created;
    }

    public function setCover(ChildcareCentre $centre, CentreGalleryItem $item): void
    {
        DB::transaction(function () use ($centre, $item): void {
            $centre->gallery()->update(['is_cover' => false]);
            $item->update(['is_cover' => true]);
        });
    }

    public function deletePhoto(CentreGalleryItem $item): void
    {
        $wasCover = $item->is_cover;
        $centre = $item->centre;
        $this->media->deletePublic($item->file_path);
        $item->delete();

        if ($wasCover) {
            $centre->gallery()->orderBy('sort_order')->first()?->update(['is_cover' => true]);
        }
    }

    public function uploadTeamPhoto(ChildcareCentre $centre, int $memberId, UploadedFile $file)
    {
        $member = $centre->teamMembers()->findOrFail($memberId);
        $old = $member->photo_path;
        $member->update(['photo_path' => $this->media->storePublic($file, "centres/{$centre->id}/team")]);
        $this->media->deletePublic($old);

        return $member;
    }

    public function addResource(ChildcareCentre $centre, UploadedFile $file, string $title, ?string $description)
    {
        return $centre->resources()->create([
            'title' => $title,
            'description' => $description,
            'file_path' => $this->media->storePublic($file, "centres/{$centre->id}/resources"),
            'mime_type' => $file->getMimeType(),
            'is_public' => true,
        ]);
    }

    /**
     * Submits the profile. Depending on moderation config it goes live now or waits for review.
     */
    public function submit(ChildcareCentre $centre): ChildcareCentre
    {
        $this->load($centre);
        $completion = $this->completion($centre);
        if (! $completion['steps']['basics']['complete']) {
            throw ValidationException::withMessages([
                'basics' => 'Complete your basic information (name, contact, address and hours) before publishing.',
            ]);
        }

        $autoPublish = config('platform.moderation.auto_publish_centres');
        $wasLive = $centre->isPubliclyVisible();

        $centre->forceFill([
            'submitted_at' => now(),
            'status' => $autoPublish || $wasLive ? 'published' : 'pending_review',
            'is_published' => $autoPublish || $wasLive,
            'published_at' => $centre->published_at ?? ($autoPublish ? now() : null),
        ])->save();

        if ($autoPublish && ! $wasLive) {
            $centre->owner->notify(new CentreProfilePublishedNotification($centre));
        }

        return $centre->refresh();
    }

    /**
     * Profile strength and per-step status shown in the builder sidebar and review step.
     */
    public function completion(ChildcareCentre $centre): array
    {
        $photos = $centre->relationLoaded('gallery') ? $centre->gallery->where('type', 'image')->count() : $centre->gallery()->where('type', 'image')->count();
        $team = $centre->relationLoaded('teamMembers') ? $centre->teamMembers : $centre->teamMembers()->get();
        $programs = $centre->relationLoaded('programs') ? $centre->programs : $centre->programs()->get();
        $faqs = $centre->relationLoaded('faqs') ? $centre->faqs : $centre->faqs()->get();
        $hasVideo = (bool) $centre->welcome_video_path;

        $basics = filled($centre->name) && filled($centre->phone) && filled($centre->email)
            && filled($centre->address) && filled($centre->city) && filled($centre->postal_code)
            && ! empty($centre->hours_of_operation);
        $about = filled($centre->about) && (filled($centre->philosophy) || ! empty($centre->teaching_approaches));
        $programsDone = $programs->where('is_active', true)->isNotEmpty();
        $teamPhotos = $team->isNotEmpty() && $team->every(fn ($member) => filled($member->photo_path));
        $parents = (filled($centre->tour_info) || filled($centre->waitlist_info)) && $faqs->isNotEmpty();

        $steps = [
            'basics' => ['complete' => $basics, 'summary' => 'Name, contact, address, hours, licence', 'recommendation' => $basics ? null : 'Add your contact details, address and hours'],
            'about' => ['complete' => $about, 'summary' => 'Story, philosophy, approach, daily routine', 'recommendation' => $about ? null : 'Tell families about your story and approach'],
            'programs' => [
                'complete' => $programsDone,
                'summary' => $programs->count().' '.Str::plural('program', $programs->count()).($programs->whereNotNull('tuition_amount')->isNotEmpty() ? ' · tuition shown' : ''),
                'recommendation' => $programsDone ? null : 'Add at least one program with ages',
            ],
            'media' => [
                'complete' => $hasVideo && $photos >= 12,
                'summary' => $photos.' '.Str::plural('photo', $photos).($hasVideo ? ' · welcome video' : ' · no welcome video'),
                'recommendation' => $hasVideo && $photos >= 12 ? null : ($photos < 12 ? 'add '.(12 - $photos).' more photos for best results' : 'add a welcome video'),
            ],
            'team' => [
                'complete' => $teamPhotos,
                'summary' => $team->count().' team '.Str::plural('member', $team->count()),
                'recommendation' => $teamPhotos ? null : ($team->isEmpty() ? 'introduce your director and educators' : 'add photos to build trust'),
            ],
            'parents' => [
                'complete' => $parents,
                'summary' => implode(', ', array_filter([
                    filled($centre->tour_info) ? 'Tours' : null,
                    filled($centre->waitlist_info) ? 'waitlist' : null,
                    $centre->relationLoaded('resources') && $centre->resources->isNotEmpty() ? 'handbook' : null,
                    $faqs->count().' '.Str::plural('FAQ', $faqs->count()),
                ])),
                'recommendation' => $parents ? null : 'Explain tours, waitlist and common questions',
            ],
        ];

        $score = ($basics ? 25 : 0) + ($about ? 15 : 0) + ($programsDone ? 15 : 0)
            + ($hasVideo ? 10 : 0) + (int) round(min($photos, 12) / 12 * 10)
            + ($team->isNotEmpty() ? 5 : 0) + ($teamPhotos ? 5 : 0)
            + ($parents ? 15 : 0);

        return ['percent' => min(100, $score), 'steps' => $steps, 'photo_count' => $photos];
    }

    private function uniqueSlug(string $name, int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'centre';
        $slug = $base;
        $i = 2;
        while (ChildcareCentre::withTrashed()->where('slug', $slug)->whereKeyNot($ignoreId)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
