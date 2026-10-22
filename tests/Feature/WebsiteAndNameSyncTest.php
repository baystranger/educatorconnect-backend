<?php

namespace Tests\Feature;

use App\Models\ProfessionalCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebsiteAndNameSyncTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides): User
    {
        $this->postJson('/api/v1/auth/register', array_merge([
            'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!',
            'city' => 'Burnaby', 'province' => 'British Columbia', 'postal_code' => 'V5H 0A1',
            'country' => 'Canada', 'terms' => true,
        ], $overrides))->assertCreated();

        $user = User::where('email', $overrides['email'])->firstOrFail();
        $user->markEmailAsVerified();

        return $user;
    }

    private function centre(): User
    {
        return $this->register([
            'role' => 'childcare_centre', 'email' => 'centre@example.com', 'centre_name' => 'Little Sprouts',
            'contact_person' => 'Anita Rao', 'phone' => '+1 604 555 0123', 'address' => '100 Main Street',
        ]);
    }

    private function basics(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Little Sprouts', 'contact_person' => 'Anita Rao', 'city' => 'Burnaby',
            'postal_code' => 'V5H 0A1', 'province' => 'British Columbia',
        ], $overrides);
    }

    public function test_educator_website_is_saved_absolute_and_shown_publicly(): void
    {
        $user = $this->register(['role' => 'educator', 'email' => 'priya@example.com', 'first_name' => 'Priya', 'last_name' => 'Nair']);

        $this->actingAs($user)->putJson('/api/v1/educator/profile', ['website' => 'priyanair.ca/portfolio'])
            ->assertOk()
            ->assertJsonPath('data.website', 'https://priyanair.ca/portfolio');

        $user->educatorProfile->update(['visibility' => 'public']);
        $this->getJson('/api/v1/educators/'.$user->educatorProfile->slug)->assertOk()
            ->assertJsonPath('data.website', 'https://priyanair.ca/portfolio');

        $this->actingAs($user)->putJson('/api/v1/educator/profile', ['website' => 'not a website'])
            ->assertStatus(422)->assertJsonValidationErrors('website');
        $this->actingAs($user)->putJson('/api/v1/educator/profile', ['website' => null])
            ->assertOk()->assertJsonPath('data.website', null);
    }

    public function test_centre_and_professional_websites_are_normalised(): void
    {
        $owner = $this->centre();
        $this->actingAs($owner)->putJson('/api/v1/centre/profile/basics', $this->basics(['website' => 'littlesprouts.ca']))
            ->assertOk()->assertJsonPath('data.form.website', 'https://littlesprouts.ca');

        ProfessionalCategory::create(['name' => 'Speech-Language Pathologists', 'slug' => 'speech-language-pathologists']);
        $pro = User::factory()->create(['role' => 'professional']);
        $pro->assignRole(Role::findOrCreate('professional', 'web'));
        $this->actingAs($pro)->postJson('/api/v1/professionals', [
            'professional_category_id' => ProfessionalCategory::value('id'), 'name' => 'Maya Chen SLP',
            'city' => 'Vancouver', 'province' => 'British Columbia', 'postal_code' => 'V5V 3M8',
            'website' => 'mayachen.ca',
        ])->assertCreated()->assertJsonPath('data.website', 'https://mayachen.ca');
    }

    public function test_renaming_a_centre_renames_the_account_display_name(): void
    {
        $owner = $this->centre();

        $this->actingAs($owner)->putJson('/api/v1/centre/profile/basics', $this->basics(['name' => 'Little Sprouts Daycare']))->assertOk();

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'name' => 'Little Sprouts Daycare', 'first_name' => 'Anita']);
        $this->actingAs($owner->refresh())->getJson('/api/v1/auth/me')->assertJsonPath('data.name', 'Little Sprouts Daycare');
    }

    public function test_renaming_the_holder_updates_a_matching_contact_person(): void
    {
        $owner = $this->centre();

        $this->actingAs($owner)->putJson('/api/v1/account', ['first_name' => 'Anita', 'last_name' => 'Rao-Singh'])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'first_name' => 'Anita', 'last_name' => 'Rao-Singh', 'name' => 'Little Sprouts']);
        $this->assertDatabaseHas('childcare_centres', ['owner_user_id' => $owner->id, 'contact_person' => 'Anita Rao-Singh']);
    }

    public function test_a_different_contact_person_is_left_alone(): void
    {
        $owner = $this->centre();

        // The centre lists its director as the public contact: the account holder stays Anita.
        $this->actingAs($owner)->putJson('/api/v1/centre/profile/basics', $this->basics(['contact_person' => 'Mei Lin']))->assertOk();
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'first_name' => 'Anita', 'last_name' => 'Rao']);

        // Anita renames herself: the contact person no longer matches, so it stays Mei Lin.
        $this->actingAs($owner->refresh())->putJson('/api/v1/account', ['first_name' => 'Anita', 'last_name' => 'Singh'])->assertOk();
        $this->assertDatabaseHas('childcare_centres', ['owner_user_id' => $owner->id, 'contact_person' => 'Mei Lin']);
    }

    public function test_educator_can_change_only_their_phone(): void
    {
        $user = $this->register(['role' => 'educator', 'email' => 'priya@example.com', 'first_name' => 'Priya', 'last_name' => 'Nair']);

        $this->actingAs($user)->putJson('/api/v1/account', ['phone' => '604 555 0199'])->assertOk()->assertJsonPath('data.phone', '604 555 0199');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '604 555 0199', 'name' => 'Priya Nair']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.updated', 'auditable_id' => $user->id]);
    }
}
