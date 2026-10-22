<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Message;
use App\Models\ParentRequest;
use App\Notifications\ApplicationSentNotification;
use App\Notifications\ApplicationViewedNotification;
use App\Notifications\ApplicationWithdrawnNotification;
use App\Notifications\CentreProfileViewedNotification;
use App\Notifications\JobApplicationSubmittedNotification;
use App\Notifications\ParentReplyNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_email_greets_the_recipient_by_their_real_name(): void
    {
        $educator = $this->educator(['first_name' => 'Rohit', 'last_name' => 'Gautam']);
        $owner = $this->centreOwner();
        $job = $this->publishedJob($owner);
        $application = $this->actingAs($educator)->postJson("/api/v1/jobs/{$job->id}/apply")->assertCreated();
        $application = $educator->applications()->firstOrFail();

        $this->assertSame('Hello, Rohit Gautam,', (new VerifyEmailNotification)->toMail($educator)->greeting);
        $this->assertSame('Hello, Rohit Gautam,', (new ResetPasswordNotification('token'))->toMail($educator)->greeting);
        $this->assertSame('Hello, Rohit Gautam,', (new ApplicationSentNotification($application))->toMail($educator)->greeting);

        // A centre account is greeted by the person who holds it, not the centre's name.
        $this->assertSame('Hello, Anita Sharma,', (new JobApplicationSubmittedNotification($application))->toMail($owner)->greeting);

        // Parents have no account: the name comes from the mail route.
        $parentRequest = ParentRequest::query()->forceCreate([
            'childcare_centre_id' => $owner->centre->id, 'type' => 'inquiry', 'parent_name' => 'Maya Patel',
            'email' => 'maya@example.com', 'message' => 'Do you have space?', 'status' => 'new',
        ]);
        $message = new Message(['body' => 'Yes, we do.']);
        $parent = (new AnonymousNotifiable)->route('mail', ['maya@example.com' => 'Maya Patel']);
        $this->assertSame('Hello, Maya Patel,', (new ParentReplyNotification($parentRequest, $message))->toMail($parent)->greeting);
    }

    public function test_hiring_events_notify_both_sides(): void
    {
        Notification::fake();
        $owner = $this->centreOwner();
        $educator = $this->educator();
        $job = $this->publishedJob($owner);

        $applicationId = $this->actingAs($educator)->postJson("/api/v1/jobs/{$job->id}/apply")->assertCreated()->json('data.id');
        Notification::assertSentTo($educator, ApplicationSentNotification::class);

        $this->actingAs($owner)->getJson("/api/v1/centre/applications/{$applicationId}")->assertOk();
        $this->actingAs($owner)->getJson("/api/v1/centre/applications/{$applicationId}")->assertOk();
        Notification::assertSentToTimes($educator, ApplicationViewedNotification::class, 1);

        $this->actingAs($educator)->postJson("/api/v1/educator/applications/{$applicationId}/withdraw")->assertOk();
        Notification::assertSentTo($owner, ApplicationWithdrawnNotification::class);
    }

    public function test_signed_in_viewer_of_a_centre_profile_is_named_to_the_centre_once_a_day(): void
    {
        Notification::fake();
        $owner = $this->centreOwner();
        $educator = $this->educator();
        $slug = $owner->centre->slug;

        $this->getJson("/api/v1/childcare-centres/{$slug}")->assertOk();
        $this->actingAs($owner)->getJson("/api/v1/childcare-centres/{$slug}")->assertOk();
        Notification::assertNotSentTo($owner, CentreProfileViewedNotification::class);

        // The sanctum guard remembers the last user within one test; a real request starts fresh.
        $this->app['auth']->forgetGuards();

        $this->actingAs($educator)->getJson("/api/v1/childcare-centres/{$slug}")->assertOk();
        $this->actingAs($educator)->getJson("/api/v1/childcare-centres/{$slug}")->assertOk();
        Notification::assertSentToTimes($owner, CentreProfileViewedNotification::class, 1);
    }

    public function test_in_app_notifications_are_written_immediately_and_can_be_filtered(): void
    {
        config(['queue.default' => 'database']);
        $owner = $this->centreOwner();
        $educator = $this->educator();
        $job = $this->publishedJob($owner);

        $this->actingAs($educator)->postJson("/api/v1/jobs/{$job->id}/apply")->assertCreated();

        // The bell entry exists without a queue worker running; only the email waits in the queue.
        $this->actingAs($owner)->getJson('/api/v1/notifications?filter=unread')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.kind', 'new_applicants');
    }

    private function publishedJob($owner): Job
    {
        $id = $this->actingAs($owner)->postJson('/api/v1/centre/jobs', $this->jobPayload(['publish' => true]))
            ->assertCreated()->json('data.id');

        return Job::findOrFail($id);
    }
}
