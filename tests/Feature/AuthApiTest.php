<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthService;
use App\Notifications\ResetPasswordNotification as ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_educator_can_register_and_receive_a_bearer_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->educatorPayload());

        $response->assertCreated()
            ->assertJsonPath('data.user.role', 'educator')
            ->assertJsonPath('data.user.educator_profile.first_name', 'Rohit')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['access_token']]);

        $this->assertDatabaseHas('educator_profiles', [
            'first_name' => 'Rohit',
            'city' => 'Burnaby',
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'model_type' => User::class,
            'model_id' => User::where('email', 'rohit@example.com')->value('id'),
        ]);
    }

    public function test_centre_can_register_against_the_centre_schema(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'role' => 'childcare_centre',
            'email' => 'centre@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'centre_name' => 'Little Sprouts',
            'contact_person' => 'Rohit Patel',
            'phone' => '+1 604 555 0123',
            'address' => '100 Main Street',
            'city' => 'Burnaby',
            'province' => 'British Columbia',
            'postal_code' => 'V5H 0A1',
            'country' => 'Canada',
            'terms' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', 'childcare_centre')
            ->assertJsonPath('data.user.childcare_centres.0.name', 'Little Sprouts');

        $this->assertDatabaseHas('childcare_centres', [
            'name' => 'Little Sprouts',
            'city' => 'Burnaby',
            'status' => 'draft',
        ]);
    }

    public function test_login_returns_a_token_and_the_token_can_access_the_current_user(): void
    {
        $this->postJson('/api/v1/auth/register', $this->educatorPayload())->assertCreated();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'ROHIT@example.com',
            'password' => 'StrongPass123!',
        ])->assertOk()
            ->assertJsonPath('data.user.email', 'rohit@example.com');

        $token = $login->json('data.access_token');
        $user = User::where('email', 'rohit@example.com')->firstOrFail();
        $tokenId = explode('|', $token, 2)[0];

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'educator');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
            'tokenable_id' => $user->id,
        ]);
        Auth::forgetGuards();
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_stateful_frontend_login_creates_a_session_for_the_current_user_route(): void
    {
        config(['sanctum.stateful' => ['localhost:5173']]);
        $this->postJson('/api/v1/auth/register', $this->educatorPayload())->assertCreated();

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/login', [
                'email' => 'rohit@example.com',
                'password' => 'StrongPass123!',
                'remember' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.access_token', null);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'rohit@example.com');

        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        Auth::forgetGuards();
        $this->withHeader('Origin', 'http://localhost:5173')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_registration_rejects_parent_role_and_weak_passwords(): void
    {
        $this->postJson('/api/v1/auth/register', array_merge($this->educatorPayload(), [
            'role' => 'parent',
        ]))->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->postJson('/api/v1/auth/register', array_merge($this->educatorPayload(), [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_oauth_login_code_is_single_use(): void
    {
        $this->postJson('/api/v1/auth/register', $this->educatorPayload())->assertCreated();
        $user = User::where('email', 'rohit@example.com')->firstOrFail();
        $auth = app(AuthService::class);
        $code = $auth->createOAuthCode($user);

        $this->postJson('/api/v1/auth/oauth/exchange', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->postJson('/api/v1/auth/oauth/exchange', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_forgot_password_sends_a_reset_notification_for_known_accounts(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/auth/register', $this->educatorPayload())->assertCreated();
        $user = User::where('email', 'rohit@example.com')->firstOrFail();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    private function educatorPayload(): array
    {
        return [
            'role' => 'educator',
            'email' => 'rohit@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'first_name' => 'Rohit',
            'last_name' => 'Patel',
            'city' => 'Burnaby',
            'province' => 'British Columbia',
            'postal_code' => 'V5H 0A1',
            'country' => 'Canada',
            'terms' => true,
        ];
    }
}
