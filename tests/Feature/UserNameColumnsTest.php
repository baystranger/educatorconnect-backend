<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PersonName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNameColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides): User
    {
        $this->postJson('/api/v1/auth/register', array_merge([
            'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!',
            'city' => 'Burnaby', 'province' => 'British Columbia', 'postal_code' => 'V5H 0A1',
            'country' => 'Canada', 'terms' => true,
        ], $overrides))->assertCreated();

        return User::where('email', $overrides['email'])->firstOrFail();
    }

    public function test_educator_signup_stores_first_and_last_name_on_the_user(): void
    {
        $user = $this->register(['role' => 'educator', 'email' => 'priya@example.com', 'first_name' => 'Priya', 'last_name' => 'Nair']);

        $this->assertSame(['Priya', 'Nair', 'Priya Nair'], [$user->first_name, $user->last_name, $user->name]);
    }

    public function test_centre_signup_takes_the_holders_name_from_the_contact_person(): void
    {
        $user = $this->register([
            'role' => 'childcare_centre', 'email' => 'centre@example.com', 'centre_name' => 'Little Sprouts',
            'contact_person' => 'Anita Mary Rao', 'phone' => '+1 604 555 0123', 'address' => '100 Main Street',
        ]);

        // `name` stays the centre's display name.
        $this->assertSame(['Anita', 'Mary Rao', 'Little Sprouts'], [$user->first_name, $user->last_name, $user->name]);
    }

    public function test_renaming_an_educator_profile_keeps_the_user_columns_in_sync(): void
    {
        $user = $this->register(['role' => 'educator', 'email' => 'priya@example.com', 'first_name' => 'Priya', 'last_name' => 'Nair']);
        $user->markEmailAsVerified();

        $this->actingAs($user)->putJson('/api/v1/account', ['first_name' => 'Priyanka', 'last_name' => 'Nair-Shah'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Priyanka')
            ->assertJsonPath('data.last_name', 'Nair-Shah');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'first_name' => 'Priyanka', 'last_name' => 'Nair-Shah', 'name' => 'Priyanka Nair-Shah']);
        $this->assertDatabaseHas('educator_profiles', ['user_id' => $user->id, 'first_name' => 'Priyanka']);
    }

    public function test_professional_can_set_first_and_last_name(): void
    {
        $user = User::factory()->create(['role' => 'professional', 'name' => 'Old Name']);

        $this->actingAs($user)->putJson('/api/v1/account', ['first_name' => 'Sam', 'last_name' => 'Lee'])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'first_name' => 'Sam', 'last_name' => 'Lee', 'name' => 'Sam Lee']);
    }

    public function test_split_handles_single_and_multi_word_names(): void
    {
        $this->assertSame(['Cher', ''], PersonName::split('Cher'));
        $this->assertSame(['Mary', 'Ann Lee'], PersonName::split('  Mary   Ann Lee '));
        $this->assertSame(['', ''], PersonName::split(''));
    }
}
