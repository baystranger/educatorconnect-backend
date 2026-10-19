<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\JobApplication;
use App\Notifications\ApplicantMessageNotification;
use App\Notifications\ApplicationStatusChangedNotification;
use App\Notifications\JobApplicationSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HiringFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_centre_posts_job_educator_applies_and_centre_schedules_interview(): void
    {
        Notification::fake();
        $owner = $this->centreOwner();
        $educator = $this->educator();

        // Draft, then publish.
        $draft = $this->actingAs($owner)->postJson('/api/v1/centre/jobs', $this->jobPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.pay', '$26–$30/hr')
            ->assertJsonPath('data.city', 'Burnaby');
        $jobId = $draft->json('data.id');

        $this->actingAs($owner)->postJson("/api/v1/centre/jobs/{$jobId}/publish")
            ->assertOk()->assertJsonPath('data.status', 'published');

        // Educator finds it within 25 km and sees facets.
        $search = $this->actingAs($educator)->getJson('/api/v1/jobs?latitude=49.2827&longitude=-123.0207&radius=25')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.is_saved', false)
            ->assertJsonPath('meta.facets.employment_types.full_time', 1);
        $this->assertLessThan(25, $search->json('data.0.distance_km'));

        $this->actingAs($educator)->getJson('/api/v1/jobs?latitude=45.5&longitude=-73.5&radius=25')
            ->assertOk()->assertJsonPath('meta.total', 0);

        // Save and apply.
        $this->actingAs($educator)->postJson("/api/v1/jobs/{$jobId}/save")->assertOk()->assertJsonPath('data.saved', true);
        $applied = $this->actingAs($educator)->postJson("/api/v1/jobs/{$jobId}/apply", ['cover_message' => 'Available weekdays.'])
            ->assertCreated()
            ->assertJsonPath('data.status.key', 'new')
            ->assertJsonPath('data.step', 1);
        $applicationId = $applied->json('data.id');
        Notification::assertSentTo($owner, JobApplicationSubmittedNotification::class);

        $this->actingAs($educator)->postJson("/api/v1/jobs/{$jobId}/apply")
            ->assertUnprocessable()->assertJsonValidationErrors('job');

        // Centre reviews: list, detail marks seen, interview.
        $this->actingAs($owner)->getJson("/api/v1/centre/jobs/{$jobId}/applicants")
            ->assertOk()
            ->assertJsonPath('meta.counts.new', 1)
            ->assertJsonPath('data.0.is_unseen', true);

        $this->actingAs($owner)->getJson("/api/v1/centre/applications/{$applicationId}")
            ->assertOk()
            ->assertJsonPath('data.educator.name', 'Priya Nair')
            ->assertJsonPath('data.cover_message', 'Available weekdays.')
            ->assertJsonPath('data.next_status.key', 'reviewed');
        $this->assertNotNull(JobApplication::find($applicationId)->centre_seen_at);

        $this->actingAs($owner)->putJson("/api/v1/centre/applications/{$applicationId}/status", ['status' => 'interview'])
            ->assertUnprocessable()->assertJsonValidationErrors('interview_at');

        $this->actingAs($owner)->putJson("/api/v1/centre/applications/{$applicationId}/status", [
            'status' => 'interview',
            'interview_at' => now()->addDays(3)->setTime(10, 0)->toIso8601String(),
            'interview_location' => '1420 Kingsway, Burnaby',
            'interview_details' => 'Ask for Anita (Director).',
        ])->assertOk()->assertJsonPath('data.status.key', 'interview');
        Notification::assertSentTo($educator, ApplicationStatusChangedNotification::class);

        // Message the applicant.
        $this->actingAs($owner)->postJson("/api/v1/centre/applications/{$applicationId}/messages", ['body' => 'See you Thursday!'])
            ->assertCreated()->assertJsonPath('data.sender_role', 'centre');
        Notification::assertSentTo($educator, ApplicantMessageNotification::class);

        // Educator sees the timeline, interview and message.
        $this->actingAs($educator)->getJson("/api/v1/educator/applications/{$applicationId}")
            ->assertOk()
            ->assertJsonPath('data.step', 4)
            ->assertJsonPath('data.interview.location', '1420 Kingsway, Burnaby')
            ->assertJsonPath('data.timeline.0.status', 'interview')
            ->assertJsonPath('data.messages.0.body', 'See you Thursday!');

        $this->actingAs($educator)->getJson('/api/v1/educator/applications?filter=interviews')
            ->assertOk()->assertJsonPath('meta.counts.interviews', 1)->assertJsonCount(1, 'data');

        $dashboard = $this->actingAs($educator)->getJson('/api/v1/educator/dashboard')->assertOk();
        $this->assertSame(1, $dashboard->json('data.stats.interviews'));
        $this->assertSame(1, $dashboard->json('data.stats.saved_jobs'));
        $this->assertNotNull($dashboard->json('data.next_up'));

        $this->actingAs($owner)->getJson('/api/v1/centre/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.open_positions', 1)
            ->assertJsonPath('data.this_week.0.kind', 'interview');

        // Withdraw.
        $this->actingAs($educator)->postJson("/api/v1/educator/applications/{$applicationId}/withdraw")
            ->assertOk()->assertJsonPath('data.status.key', 'withdrawn');
    }

    public function test_unverified_educator_cannot_apply(): void
    {
        $owner = $this->centreOwner();
        $job = $this->publishedJob($owner);
        $educator = $this->educator(verified: false);

        $this->actingAs($educator)->postJson("/api/v1/jobs/{$job->id}/apply")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('email');
    }

    public function test_publish_requires_the_checklist(): void
    {
        $owner = $this->centreOwner();
        $job = $this->actingAs($owner)->postJson('/api/v1/centre/jobs', ['title' => 'Casual ECE Assistant'])
            ->assertCreated()->json('data.id');

        $this->actingAs($owner)->postJson("/api/v1/centre/jobs/{$job}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publish');
    }

    public function test_roles_and_ownership_are_enforced_on_the_server(): void
    {
        $owner = $this->centreOwner();
        $otherOwner = $this->centreOwner(['centre_name' => 'Maple Grove Montessori']);
        $educator = $this->educator();
        $job = $this->publishedJob($owner);

        $this->actingAs($educator)->getJson('/api/v1/centre/dashboard')->assertForbidden()->assertJsonPath('success', false);
        $this->actingAs($owner)->getJson('/api/v1/educator/dashboard')->assertForbidden();
        $this->actingAs($owner)->postJson("/api/v1/jobs/{$job->id}/apply")->assertForbidden();

        $this->actingAs($otherOwner)->putJson("/api/v1/centre/jobs/{$job->id}", $this->jobPayload())->assertForbidden();
        $this->actingAs($otherOwner)->getJson("/api/v1/centre/jobs/{$job->id}/applicants")->assertForbidden();

        $application = $this->actingAs($educator)->postJson("/api/v1/jobs/{$job->id}/apply")->json('data.id');
        $this->actingAs($otherOwner)->getJson("/api/v1/centre/applications/{$application}")->assertForbidden();

        $intruder = $this->educator(['first_name' => 'Jordan']);
        $this->actingAs($intruder)->getJson("/api/v1/educator/applications/{$application}")->assertForbidden();
    }

    public function test_centre_job_tabs_close_archive_duplicate_and_delete_draft(): void
    {
        $owner = $this->centreOwner();
        $job = $this->publishedJob($owner);

        $this->actingAs($owner)->postJson("/api/v1/centre/jobs/{$job->id}/duplicate")->assertOk()->assertJsonPath('data.status', 'draft');
        $this->actingAs($owner)->postJson("/api/v1/centre/jobs/{$job->id}/close")->assertOk()->assertJsonPath('data.status', 'closed');

        $this->actingAs($owner)->getJson('/api/v1/centre/jobs')
            ->assertOk()
            ->assertJsonPath('meta.counts.active', 2)
            ->assertJsonPath('meta.counts.closed', 1)
            ->assertJsonPath('meta.counts.draft', 1);

        $draftId = Job::where('status', 'draft')->value('id');
        $this->actingAs($owner)->deleteJson("/api/v1/centre/jobs/{$draftId}")->assertOk();
        $this->actingAs($owner)->deleteJson("/api/v1/centre/jobs/{$job->id}")->assertUnprocessable();

        $this->actingAs($owner)->postJson("/api/v1/centre/jobs/{$job->id}/archive")->assertOk();
        $this->getJson('/api/v1/jobs/'.$job->slug)->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_unverified_centre_cannot_publish_a_job_by_any_route(): void
    {
        $owner = $this->centreOwner();
        $owner->forceFill(['email_verified_at' => null])->save();

        // Create with publish: refused before anything is saved.
        $this->actingAs($owner)->postJson('/api/v1/centre/jobs', $this->jobPayload(['publish' => true]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('publish');
        $this->assertSame(0, Job::count());

        // Drafts are still allowed.
        $draft = $this->actingAs($owner)->postJson('/api/v1/centre/jobs', $this->jobPayload())
            ->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

        // Update with publish: refused, the job stays a draft.
        $this->actingAs($owner)->putJson("/api/v1/centre/jobs/{$draft}", $this->jobPayload(['publish' => true]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('publish');
        $this->assertSame('draft', Job::findOrFail($draft)->status);

        // The publish action: refused.
        $this->actingAs($owner)->postJson("/api/v1/centre/jobs/{$draft}/publish")->assertForbidden();
        $this->assertSame('draft', Job::findOrFail($draft)->status);

        // Once verified, the same draft publishes.
        $owner->markEmailAsVerified();
        $this->actingAs($owner)->putJson("/api/v1/centre/jobs/{$draft}", $this->jobPayload(['publish' => true]))
            ->assertOk()->assertJsonPath('data.status', 'published');
    }

    private function publishedJob($owner): Job
    {
        $id = $this->actingAs($owner)->postJson('/api/v1/centre/jobs', $this->jobPayload(['publish' => true]))
            ->assertCreated()->json('data.id');

        return Job::findOrFail($id);
    }
}
