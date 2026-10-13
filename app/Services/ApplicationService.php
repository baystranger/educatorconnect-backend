<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use App\Notifications\ApplicationStatusChangedNotification;
use App\Notifications\JobApplicationSubmittedNotification;
use App\Support\ApplicationStages;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The hiring pipeline between an educator and a centre.
 */
class ApplicationService
{
    /**
     * @param  array{video?: bool, certifications?: bool}  $share  what the educator shares beyond their profile
     */
    public function apply(Job $job, User $educator, ?string $coverMessage, array $share = []): JobApplication
    {
        if (! $job->isAcceptingApplications()) {
            throw ValidationException::withMessages(['job' => 'This job is no longer accepting applications.']);
        }
        if (! $educator->educatorProfile) {
            throw ValidationException::withMessages(['profile' => 'Create your educator profile before applying.']);
        }

        return DB::transaction(function () use ($job, $educator, $coverMessage, $share): JobApplication {
            $application = JobApplication::query()
                ->where('job_id', $job->id)
                ->where('educator_id', $educator->id)
                ->lockForUpdate()
                ->first();

            if ($application && $application->status !== 'withdrawn') {
                throw ValidationException::withMessages(['job' => 'You have already applied to this job.']);
            }

            $attributes = [
                'cover_message' => $coverMessage,
                'resume_path' => $educator->educatorProfile->resume_path,
                'shared_items' => [
                    'video' => (bool) ($share['video'] ?? true),
                    'certifications' => (bool) ($share['certifications'] ?? true),
                ],
                'status' => 'new',
                'applied_at' => now(),
                'status_changed_at' => now(),
                'withdrawn_at' => null,
                'centre_seen_at' => null,
                'interview_at' => null,
            ];

            if ($application) {
                $application->update($attributes);
            } else {
                $application = $job->applications()->create($attributes + ['educator_id' => $educator->id]);
            }

            $application->events()->create(['status' => 'new', 'actor_user_id' => $educator->id]);
            $job->centre->owner->notify(new JobApplicationSubmittedNotification($application));

            return $application->load(['job.centre', 'events']);
        });
    }

    public function withdraw(JobApplication $application, User $educator): JobApplication
    {
        if (! in_array($application->status, ApplicationStages::ACTIVE, true)) {
            throw ValidationException::withMessages(['application' => 'This application can no longer be withdrawn.']);
        }

        DB::transaction(function () use ($application, $educator): void {
            $application->update(['status' => 'withdrawn', 'withdrawn_at' => now(), 'status_changed_at' => now()]);
            $application->events()->create(['status' => 'withdrawn', 'actor_user_id' => $educator->id]);
        });

        return $application->refresh();
    }

    /**
     * Centre moves an applicant through the pipeline (optionally scheduling an interview).
     */
    public function changeStatus(JobApplication $application, User $actor, array $data): JobApplication
    {
        $status = $data['status'];
        if ($application->status === 'withdrawn') {
            throw ValidationException::withMessages(['status' => 'The educator withdrew this application.']);
        }

        DB::transaction(function () use ($application, $actor, $data, $status): void {
            $attributes = ['status' => $status, 'status_changed_at' => now(), 'centre_seen_at' => $application->centre_seen_at ?? now()];
            if ($status === 'reviewed' && ! $application->reviewed_at) {
                $attributes['reviewed_at'] = now();
            }
            if ($status === 'interview') {
                $attributes += [
                    'interview_at' => $data['interview_at'] ?? $application->interview_at,
                    'interview_location' => $data['interview_location'] ?? $application->interview_location,
                    'interview_details' => $data['interview_details'] ?? $application->interview_details,
                ];
            }

            $changed = $application->status !== $status;
            $application->update($attributes);

            if ($changed || $status === 'interview') {
                $application->events()->create([
                    'status' => $status,
                    'note' => $status === 'interview' && $application->interview_at
                        ? $application->interview_at->timezone(config('platform.timezone'))->format('D, M j, g:i A')
                        : null,
                    'actor_user_id' => $actor->id,
                ]);
                $application->educator->notify(new ApplicationStatusChangedNotification($application));
            }
        });

        return $application->refresh();
    }

    public function markSeen(JobApplication $application): void
    {
        if ($application->centre_seen_at === null) {
            $application->forceFill(['centre_seen_at' => now()])->save();
        }
    }
}
