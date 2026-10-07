<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Models\ChildcareCentre;
use App\Models\Job;
use App\Repositories\Contracts\JobRepositoryInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Job posting lifecycle for centres: draft → published → closed → archived.
 */
class JobService
{
    public function __construct(
        private readonly JobRepositoryInterface $jobs,
        private readonly LocationService $locations,
    ) {}

    public function create(ChildcareCentre $centre, array $data, bool $publish): Job
    {
        return DB::transaction(function () use ($centre, $data, $publish): Job {
            $attributes = $this->attributes($centre, $data);
            $attributes['childcare_centre_id'] = $centre->id;
            $attributes['slug'] = $this->uniqueSlug($attributes['title'] ?? 'job');
            $attributes['status'] = JobStatus::DRAFT->value;

            $job = $this->jobs->create($attributes);

            return $publish ? $this->publish($job) : $job->refresh();
        });
    }

    public function update(Job $job, array $data, bool $publish): Job
    {
        return DB::transaction(function () use ($job, $data, $publish): Job {
            $job = $this->jobs->update($job, $this->attributes($job->centre, $data));

            return $publish && $job->status !== JobStatus::PUBLISHED->value ? $this->publish($job) : $job;
        });
    }

    public function publish(Job $job): Job
    {
        $missing = $this->missingForPublish($job);
        if ($missing) {
            throw ValidationException::withMessages(['publish' => 'Add '.implode(', ', $missing).' before publishing.']);
        }

        $job->forceFill([
            'status' => JobStatus::PUBLISHED->value,
            'published_at' => $job->published_at && $job->status === JobStatus::CLOSED->value ? $job->published_at : now(),
            'closed_at' => null,
            'archived_at' => null,
        ])->save();

        return $job->refresh();
    }

    public function close(Job $job): Job
    {
        $job->forceFill(['status' => JobStatus::CLOSED->value, 'closed_at' => now()])->save();

        return $job->refresh();
    }

    public function archive(Job $job): Job
    {
        $job->forceFill(['status' => JobStatus::ARCHIVED->value, 'archived_at' => now(), 'closed_at' => $job->closed_at ?? now()])->save();

        return $job->refresh();
    }

    public function duplicate(Job $job): Job
    {
        $copy = $job->replicate(['slug', 'status', 'published_at', 'closed_at', 'archived_at', 'views']);
        $copy->title = Str::limit($job->title.' (copy)', 250, '');
        $copy->slug = $this->uniqueSlug($copy->title);
        $copy->status = JobStatus::DRAFT->value;
        $copy->views = 0;
        $copy->save();

        return $copy;
    }

    /** Drafts can be deleted outright; postings with applicants are archived instead. */
    public function delete(Job $job): void
    {
        if ($job->status !== JobStatus::DRAFT->value || $job->applications()->exists()) {
            throw ValidationException::withMessages(['job' => 'Only drafts without applicants can be deleted. Archive this posting instead.']);
        }
        $job->delete();
    }

    /**
     * The "Ready to publish?" checklist.
     *
     * @return string[] human-readable names of missing items
     */
    public function missingForPublish(Job $job): array
    {
        return array_keys(array_filter([
            'a job title' => blank($job->title),
            'an employment type' => blank($job->employment_type),
            'a location' => blank($job->city) || blank($job->postal_code),
            'a description' => blank($job->description),
            'an application deadline' => $job->application_deadline === null,
        ]));
    }

    private function attributes(ChildcareCentre $centre, array $data): array
    {
        $attributes = Arr::only($data, [
            'title', 'category', 'employment_type', 'number_of_positions', 'start_date', 'application_deadline',
            'salary_min', 'salary_max', 'salary_period', 'show_pay', 'description', 'responsibilities', 'benefits',
            'required_education', 'experience_level', 'required_certifications', 'application_instructions',
            'practicum_level', 'practicum_hours', 'partner_institutions',
        ]);

        if (($data['use_centre_address'] ?? true) === true) {
            $attributes += [
                'city' => $centre->city,
                'province' => $centre->province,
                'postal_code' => $centre->postal_code,
                'country' => $centre->country ?: 'Canada',
                'latitude' => $centre->latitude,
                'longitude' => $centre->longitude,
            ];
        } else {
            $location = Arr::only($data, ['city', 'province', 'postal_code', 'country']);
            $geo = $this->locations->geocode(implode(', ', array_filter([
                $data['address'] ?? null, $location['city'] ?? null, $location['province'] ?? null, $location['postal_code'] ?? null,
            ])));
            $attributes += $location + ['country' => 'Canada'] + ($geo ?? ['latitude' => null, 'longitude' => null]);
        }

        if (($attributes['employment_type'] ?? null) !== 'practicum' && array_key_exists('employment_type', $attributes)) {
            $attributes['practicum_level'] = null;
            $attributes['practicum_hours'] = null;
            $attributes['partner_institutions'] = null;
        }

        return $attributes;
    }

    private function uniqueSlug(string $title): string
    {
        return (Str::slug($title) ?: 'job').'-'.Str::lower(Str::random(6));
    }
}
