<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EducatorProfileAndAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_educator_builds_profile_and_completion_updates(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $educator = $this->educator();

        $this->actingAs($educator)->getJson('/api/v1/educator/profile')
            ->assertOk()->assertJsonPath('data.completion.percent', 0);

        $this->actingAs($educator)->putJson('/api/v1/educator/profile', [
            'professional_title' => 'Infant / Toddler Educator',
            'years_experience' => 6,
            'languages' => ['English', 'Hindi'],
            'professional_summary' => 'Infant and toddler educator with six years in group care.',
            'employment_preferences' => ['full_time', 'part_time'],
            'areas_of_expertise' => ['Primary caregiving'],
            'professional_development' => ['Circle of Security (2025)'],
            'visibility' => 'public',
            'student' => ['enabled' => true, 'practicum_level' => 'level_2'],
        ])->assertOk()
            ->assertJsonPath('data.languages.1', 'Hindi')
            ->assertJsonPath('data.professional_development.0', 'Circle of Security (2025)')
            ->assertJsonPath('data.student.practicum_level', 'level_2');

        $this->actingAs($educator)->putJson('/api/v1/educator/profile', ['employment_preferences' => ['astronaut']])
            ->assertUnprocessable()->assertJsonValidationErrors('employment_preferences.0');

        $this->actingAs($educator)->postJson('/api/v1/educator/credentials', [
            'kind' => 'certification', 'title' => 'Early Childhood Educator Certificate', 'issuer' => 'BC ECE Registry',
            'expires_on' => now()->addYears(2)->toDateString(),
        ])->assertCreated()->assertJsonPath('data.badge', 'Current');

        $this->actingAs($educator)->postJson('/api/v1/educator/profile/media/resume', [
            'file' => UploadedFile::fake()->create('resume.pdf', 120, 'application/pdf'),
        ])->assertOk()->assertJsonPath('data.resume.name', 'resume.pdf');

        $this->actingAs($educator)->postJson('/api/v1/educator/profile/media/resume', [
            'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ])->assertUnprocessable();

        $profile = $this->actingAs($educator)->getJson('/api/v1/educator/profile')->assertOk();
        $this->assertSame(80, $profile->json('data.completion.percent'));
        $this->actingAs($educator)->get('/api/v1/educator/profile/resume')->assertOk();

        // Practicum mode and dashboard.
        $this->actingAs($educator)->putJson('/api/v1/educator/profile/practicum-mode', ['enabled' => true])
            ->assertOk()->assertJsonPath('data.student.looking_for_practicum', true);
        $this->actingAs($educator)->getJson('/api/v1/educator/dashboard')
            ->assertOk()->assertJsonPath('data.practicum.enabled', true)->assertJsonPath('data.completion.percent', 80);
    }

    public function test_profile_visibility_is_enforced_server_side(): void
    {
        Notification::fake();
        $educator = $this->educator();
        $slug = $educator->educatorProfile->slug;
        $centre = $this->centreOwner();

        // Default visibility is verified centres only.
        $this->getJson("/api/v1/educators/{$slug}")->assertNotFound();
        $this->actingAs($centre)->getJson("/api/v1/educators/{$slug}")->assertNotFound();
        Auth::forgetGuards();

        $this->centreOf($centre)->forceFill(['is_verified' => true])->save();
        $this->actingAs($centre)->getJson("/api/v1/educators/{$slug}")->assertOk()->assertJsonPath('data.full_name', 'Priya Nair');

        $educator->educatorProfile->update(['visibility' => 'applied_only']);
        $this->actingAs($centre)->getJson("/api/v1/educators/{$slug}")->assertNotFound();

        $job = $this->actingAs($centre)->postJson('/api/v1/centre/jobs', $this->jobPayload(['publish' => true]))->json('data.id');
        JobApplication::create(['job_id' => $job, 'educator_id' => $educator->id, 'status' => 'new', 'applied_at' => now()]);
        $this->actingAs($centre)->getJson("/api/v1/educators/{$slug}")->assertOk();

        // The owner can always preview.
        $this->actingAs($educator)->getJson("/api/v1/educators/{$slug}")->assertOk();
    }

    public function test_account_settings_password_notifications_and_deactivation(): void
    {
        Notification::fake();
        $educator = $this->educator();

        $this->actingAs($educator)->putJson('/api/v1/account', ['first_name' => 'Priyanka', 'phone' => '(604) 555-0101', 'city' => 'Vancouver'])
            ->assertOk()->assertJsonPath('data.name', 'Priyanka Nair');

        $this->actingAs($educator)->putJson('/api/v1/account/password', [
            'current_password' => 'wrong-password', 'password' => 'NewStrongPass456!', 'password_confirmation' => 'NewStrongPass456!',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->actingAs($educator)->putJson('/api/v1/account/password', [
            'current_password' => self::PASSWORD, 'password' => 'NewStrongPass456!', 'password_confirmation' => 'NewStrongPass456!',
        ])->assertOk();

        $this->actingAs($educator)->getJson('/api/v1/account/notification-preferences')
            ->assertOk()->assertJsonPath('data.preferences.types.profile_views.email', false);
        $this->actingAs($educator)->putJson('/api/v1/account/notification-preferences', [
            'types' => ['profile_views' => ['email' => true], 'unknown_type' => ['email' => true]],
            'job_alert_frequency' => 'weekly',
        ])->assertOk()
            ->assertJsonPath('data.types.profile_views.email', true)
            ->assertJsonPath('data.job_alert_frequency', 'weekly');

        $this->actingAs($educator)->get('/api/v1/account/export')->assertOk()->assertHeader('Content-Disposition');

        $this->actingAs($educator)->postJson('/api/v1/account/deactivate', ['password' => 'NewStrongPass456!'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->actingAs($educator)->postJson('/api/v1/account/deactivate', ['password' => 'NewStrongPass456!', 'confirm' => true])->assertOk();
        $this->assertSame('deactivated', $educator->refresh()->status);
        Auth::forgetGuards();

        // Signing in again within 30 days reactivates.
        $this->postJson('/api/v1/auth/login', ['email' => $educator->email, 'password' => 'NewStrongPass456!'])->assertOk();
        $this->assertSame('active', $educator->refresh()->status);
    }

    public function test_email_change_requires_reverification_and_signed_link_verifies(): void
    {
        Notification::fake();
        $educator = $this->educator();

        $this->actingAs($educator)->putJson('/api/v1/account/email', ['email' => 'new@example.com', 'current_password' => self::PASSWORD])
            ->assertOk()->assertJsonPath('data.email_verified', false);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $educator->id, 'hash' => sha1('new@example.com'),
        ]);
        $this->get($url)->assertRedirectContains('/verify-email?status=verified');
        $this->assertTrue($educator->refresh()->hasVerifiedEmail());

        $this->get('/api/v1/auth/email/verify/'.$educator->id.'/'.sha1('new@example.com'))->assertForbidden();
    }

    public function test_api_errors_use_the_shared_envelope(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()
            ->assertExactJson(['success' => false, 'message' => 'Please sign in to continue.']);

        $this->getJson('/api/v1/jobs/does-not-exist')->assertNotFound()->assertJsonPath('success', false);
        $this->getJson('/api/v1/nope')->assertNotFound()->assertJsonPath('success', false);

        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors' => ['email', 'password']]);

        $user = User::first();
        $this->assertNull($user);
        $this->getJson('/api/v1/meta/options')->assertOk()->assertJsonPath('data.employment_types.0.key', 'full_time');
    }
}
