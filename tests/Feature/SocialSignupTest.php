<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification as VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'frontend.url' => 'https://app.test',
            'services.google.client_id' => 'id',
            'services.google.client_secret' => 'secret',
        ]);
    }

    public function test_google_signup_returns_to_the_form_without_creating_an_account(): void
    {
        $token = $this->connectGoogle('educator');

        $this->assertSame(0, User::count());
        $this->postJson('/api/v1/auth/oauth/pending', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.provider', 'google')
            ->assertJsonPath('data.email', 'maya@example.com')
            ->assertJsonPath('data.first_name', 'Maya')
            ->assertJsonPath('data.last_name', 'Chen')
            ->assertJsonPath('data.role', 'educator');
    }

    public function test_educator_finishes_google_signup_with_location_details(): void
    {
        Notification::fake();
        $token = $this->connectGoogle('educator');

        $this->postJson('/api/v1/auth/register/social', [
            'token' => $token,
            'city' => 'Burnaby',
            'postal_code' => 'V5H 0A1',
            'country' => 'Canada',
            'province' => 'British Columbia',
            'is_student' => true,
            'terms' => true,
        ])->assertCreated()
            ->assertJsonPath('data.user.role', 'educator')
            ->assertJsonPath('data.user.email', 'maya@example.com')
            ->assertJsonPath('data.user.email_verified', false);

        $user = User::where('email', 'maya@example.com')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertDatabaseHas('educator_profiles', [
            'user_id' => $user->id, 'first_name' => 'Maya', 'last_name' => 'Chen',
            'city' => 'Burnaby', 'postal_code' => 'V5H 0A1', 'province' => 'British Columbia', 'country' => 'Canada',
        ]);
        $this->assertDatabaseHas('student_profiles', ['enabled' => true]);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'g-123']);
        // Verified the same way as a form sign-up.
        $this->assertNull($user->email_verified_at);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        // The token is single-use.
        $this->postJson('/api/v1/auth/oauth/pending', ['token' => $token])->assertStatus(422);
    }

    public function test_centre_google_signup_requires_centre_details(): void
    {
        $token = $this->connectGoogle('childcare_centre');
        $location = ['token' => $token, 'city' => 'Vancouver', 'postal_code' => 'V5K 0A1', 'country' => 'Canada', 'province' => 'British Columbia', 'terms' => true];

        $this->postJson('/api/v1/auth/register/social', $location)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['centre_name', 'phone', 'address']);

        $this->postJson('/api/v1/auth/register/social', $location + [
            'centre_name' => 'Cedar & Moss', 'phone' => '(604) 555-0123', 'address' => '12 Main St',
        ])->assertCreated()->assertJsonPath('data.user.role', 'childcare_centre');

        $this->assertDatabaseHas('childcare_centres', [
            'name' => 'Cedar & Moss', 'contact_person' => 'Maya Chen', 'address' => '12 Main St', 'city' => 'Vancouver',
        ]);
    }

    public function test_known_google_identity_signs_in_instead_of_signing_up(): void
    {
        $token = $this->connectGoogle('educator');
        $this->postJson('/api/v1/auth/register/social', [
            'token' => $token, 'city' => 'Burnaby', 'postal_code' => 'V5H 0A1', 'country' => 'Canada', 'province' => 'British Columbia', 'terms' => true,
        ])->assertCreated();

        $this->mockGoogleUser();
        $this->withSession(['oauth_flow' => ['intent' => 'signup', 'role' => 'educator', 'user_id' => null]])
            ->get('/oauth/google/callback?code=x&state=y')
            ->assertRedirectContains('https://app.test/auth/callback?code=');
    }

    public function test_google_signup_for_an_existing_email_returns_to_signup_with_an_error(): void
    {
        User::factory()->create(['email' => 'maya@example.com', 'role' => 'educator', 'status' => 'active']);
        $this->mockGoogleUser();

        $this->withSession(['oauth_flow' => ['intent' => 'signup', 'role' => 'childcare_centre', 'user_id' => null]])
            ->get('/oauth/google/callback?code=x&state=y')
            ->assertRedirect('https://app.test/signup?type=centre&oauth_error=account_conflict');
    }

    public function test_expired_pending_signup_is_rejected(): void
    {
        $token = $this->connectGoogle('educator');
        $this->travel(31)->minutes();

        $this->postJson('/api/v1/auth/register/social', [
            'token' => $token, 'city' => 'Burnaby', 'postal_code' => 'V5H 0A1', 'country' => 'Canada', 'province' => 'British Columbia', 'terms' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['token']);
        $this->assertSame(0, User::count());
    }

    public function test_google_signup_is_verified_through_the_emailed_link(): void
    {
        Notification::fake();
        $token = $this->connectGoogle('educator');
        $this->postJson('/api/v1/auth/register/social', [
            'token' => $token, 'city' => 'Burnaby', 'postal_code' => 'V5H 0A1', 'country' => 'Canada', 'province' => 'British Columbia', 'terms' => true,
        ])->assertCreated();

        $user = User::where('email', 'maya@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ]);
        $this->get($link)->assertRedirect('https://app.test/verify-email?status=verified');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_retried_signup_after_a_lost_response_signs_in_the_same_account(): void
    {
        Notification::fake();
        $token = $this->connectGoogle('educator');
        $details = ['token' => $token, 'city' => 'Burnaby', 'postal_code' => 'V5H 0A1', 'country' => 'Canada', 'province' => 'British Columbia', 'terms' => true];

        $first = $this->postJson('/api/v1/auth/register/social', $details)->assertCreated()->json('data.user.id');
        $this->postJson('/api/v1/auth/register/social', $details)->assertCreated()->assertJsonPath('data.user.id', $first);
        $this->assertSame(1, User::count());
        // The retry doesn't send a second verification email.
        Notification::assertSentToTimes(User::findOrFail($first), VerifyEmail::class, 1);

        // Not once the 30-minute window has passed.
        $this->travel(31)->minutes();
        $this->postJson('/api/v1/auth/register/social', $details)->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    public function test_callback_puts_the_profile_in_the_fragment_not_the_query(): void
    {
        $this->mockGoogleUser();
        $location = $this->withSession(['oauth_flow' => ['intent' => 'signup', 'role' => 'educator', 'user_id' => null]])
            ->get('/oauth/google/callback?code=x&state=y')
            ->headers->get('Location');

        $this->assertSame('https://app.test/signup?type=educator', strtok($location, '#'));
        parse_str((string) parse_url($location, PHP_URL_FRAGMENT), $fragment);
        $this->assertSame(['google', 'educator', 'maya@example.com', 'Maya', 'Chen'], [
            $fragment['provider'], $fragment['role'], $fragment['email'], $fragment['first_name'], $fragment['last_name'],
        ]);
        $this->assertSame(64, strlen($fragment['oauth']));
    }

    /** Runs the provider callback and returns the pending sign-up token from the redirect. */
    private function connectGoogle(string $role): string
    {
        $this->mockGoogleUser();
        $location = $this->withSession(['oauth_flow' => ['intent' => 'signup', 'role' => $role, 'user_id' => null]])
            ->get('/oauth/google/callback?code=x&state=y')
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('https://app.test/signup?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame($role === 'childcare_centre' ? 'centre' : $role, $query['type']);
        parse_str((string) parse_url($location, PHP_URL_FRAGMENT), $fragment);

        return $fragment['oauth'];
    }

    private function mockGoogleUser(): void
    {
        $socialUser = (new SocialiteUser)->setRaw(['given_name' => 'Maya', 'family_name' => 'Chen'])->map([
            'id' => 'g-123', 'name' => 'Maya Chen', 'email' => 'maya@example.com',
        ]);
        Socialite::shouldReceive('driver->user')->andReturn($socialUser);
    }
}
