<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Exercises every read endpoint against realistic data. Lazy loading is disabled outside
 * production, so an N+1 query path fails this test.
 */
class DemoDataSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(DemoSeeder::class);
    }

    public function test_public_endpoints(): void
    {
        $this->getJson('/api/v1/meta/options')->assertOk();
        $this->getJson('/api/v1/jobs')->assertOk()->assertJsonPath('meta.facets.employment_types.practicum', 2);
        $this->getJson('/api/v1/jobs?latitude=49.2488&longitude=-123.0016&radius=10&sort=closest&employment_types=full_time,part_time')->assertOk();
        $this->getJson('/api/v1/jobs?keyword=practicum&posted_within=30')->assertOk();
        $this->getJson('/api/v1/childcare-centres?latitude=49.2276&longitude=-123.0076&radius=10')->assertOk()->assertJsonPath('data.0.slug', 'little-sprouts');
        $this->getJson('/api/v1/childcare-centres?sort=availability&has_video=0')->assertOk();
        $this->getJson('/api/v1/childcare-centres/little-sprouts')
            ->assertOk()
            ->assertJsonCount(4, 'data.team')
            ->assertJsonCount(4, 'data.faqs')
            ->assertJsonPath('data.availability_detail.has_spaces', true);
        $this->getJson('/api/v1/childcare-centres/little-sprouts/jobs')->assertOk();
        $this->getJson('/api/v1/professionals?featured=1')->assertOk();
        $this->getJson('/api/v1/professionals/categories')->assertOk();
    }

    public function test_educator_endpoints(): void
    {
        $priya = User::where('email', 'educator@demo.educatorconnect.ca')->firstOrFail();

        $dashboard = $this->actingAs($priya)->getJson('/api/v1/educator/dashboard')->assertOk();
        $this->assertSame(4, $dashboard->json('data.stats.active_applications'));
        $this->assertSame('Little Sprouts Daycare', $dashboard->json('data.next_up.centre'));
        $this->assertCount(3, $dashboard->json('data.recommended'));

        $this->actingAs($priya)->getJson('/api/v1/educator/applications')->assertOk()->assertJsonPath('meta.counts.all', 5);
        $this->actingAs($priya)->getJson('/api/v1/educator/saved-jobs')->assertOk();
        $this->actingAs($priya)->getJson('/api/v1/educator/profile')->assertOk()->assertJsonCount(4, 'data.credentials');
        $this->actingAs($priya)->getJson('/api/v1/jobs?latitude=49.248&longitude=-123.03&radius=25')->assertOk();
        $this->actingAs($priya)->getJson('/api/v1/notifications')->assertOk();
        $this->actingAs($priya)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.short_name', 'Priya');
    }

    public function test_centre_endpoints(): void
    {
        $owner = User::where('email', 'centre@demo.educatorconnect.ca')->firstOrFail();
        $jobId = $owner->centre->jobs()->where('title', 'Early Childhood Educator')->value('id');

        $dashboard = $this->actingAs($owner)->getJson('/api/v1/centre/dashboard')->assertOk();
        $this->assertGreaterThan(0, $dashboard->json('data.attention.counts.hiring'));
        $this->assertCount(8, $dashboard->json('data.views_weekly'));

        $this->actingAs($owner)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.short_name', 'Little Sprouts');
        $this->actingAs($owner)->getJson('/api/v1/centre/profile')->assertOk();
        $this->actingAs($owner)->getJson('/api/v1/centre/jobs')->assertOk()->assertJsonPath('meta.counts.published', 3);
        $this->actingAs($owner)->getJson("/api/v1/centre/jobs/{$jobId}")->assertOk();
        $this->actingAs($owner)->getJson("/api/v1/centre/jobs/{$jobId}/applicants")->assertOk()->assertJsonCount(4, 'data');

        $application = JobApplication::where('job_id', $jobId)->firstOrFail();
        $this->actingAs($owner)->getJson("/api/v1/centre/applications/{$application->id}")->assertOk();
        $this->actingAs($owner)->getJson('/api/v1/centre/families?type=inquiry')->assertOk()->assertJsonCount(3, 'data');
        $this->actingAs($owner)->getJson('/api/v1/centre/families?type=tour')->assertOk()->assertJsonPath('meta.counts.waitlist', 2);
        $this->actingAs($owner)->getJson('/api/v1/account/notification-preferences')->assertOk();
    }
}
