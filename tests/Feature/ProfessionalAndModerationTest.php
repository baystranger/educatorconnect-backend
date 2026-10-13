<?php

namespace Tests\Feature;

use App\Models\ProfessionalCategory;
use App\Models\ProfessionalListing;
use App\Models\User;
use App\Notifications\ProfessionalListingReviewedNotification;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfessionalAndModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_register_without_a_role_profile(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'role' => 'professional',
            'email' => 'sam@therapy.example',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'first_name' => 'Sam',
            'last_name' => 'Rivera',
            'city' => 'Vancouver',
            'province' => 'British Columbia',
            'postal_code' => 'V6B 1A1',
            'country' => 'Canada',
            'terms' => true,
        ])->assertCreated()->assertJsonPath('data.user.role', 'professional');

        $user = User::where('email', 'sam@therapy.example')->firstOrFail();
        $this->assertTrue($user->hasRole('professional'));
        $this->assertNull($user->educatorProfile);
        $this->assertSame(0, $user->childcareCentres()->count());

        $this->postJson('/api/v1/auth/register', ['role' => 'professional', 'email' => 'x@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    public function test_listing_is_reviewed_before_it_appears_in_the_directory(): void
    {
        Notification::fake();
        $owner = $this->professional();
        $admin = $this->administrator();

        $listingId = $this->actingAs($owner)->postJson('/api/v1/professionals', $this->listingPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.is_live', false)
            ->json('data.id');

        $this->getJson('/api/v1/professionals?keyword=Rivera')->assertOk()->assertJsonPath('meta.total', 0);
        $this->actingAs($owner)->getJson('/api/v1/professional/listings')
            ->assertOk()->assertJsonCount(1, 'data.listings')->assertJsonPath('data.listings.0.status_label', 'In review');
        $this->actingAs($admin)->getJson('/api/v1/admin/overview')->assertOk()->assertJsonPath('data.listings_pending', 1);

        // Rejection needs a reason, and the owner hears about it.
        $this->actingAs($admin)->postJson("/api/v1/admin/listings/{$listingId}/reject")
            ->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->actingAs($admin)->postJson("/api/v1/admin/listings/{$listingId}/reject", ['notes' => 'Add your registration number.'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        Notification::assertSentTo($owner, ProfessionalListingReviewedNotification::class, fn ($n) => ! $n->approved);

        // Editing a rejected listing puts it back in the queue.
        $this->actingAs($owner)->putJson("/api/v1/professional/listings/{$listingId}", $this->listingPayload(['credentials' => ['SAC-BC #12345']]))
            ->assertOk()->assertJsonPath('data.status', 'pending_review')->assertJsonPath('data.review_notes', null);

        $this->actingAs($admin)->postJson("/api/v1/admin/listings/{$listingId}/approve")
            ->assertOk()->assertJsonPath('data.status', 'claimed_verified')->assertJsonPath('data.is_live', true);
        Notification::assertSentTo($owner, ProfessionalListingReviewedNotification::class, fn ($n) => $n->approved);

        $this->getJson('/api/v1/professionals?keyword=Rivera')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.listing_status', 'verified');
    }

    public function test_only_the_owner_can_manage_a_listing(): void
    {
        $owner = $this->professional();
        $listingId = $this->actingAs($owner)->postJson('/api/v1/professionals', $this->listingPayload())->json('data.id');

        $this->actingAs($this->professional())->putJson("/api/v1/professional/listings/{$listingId}", $this->listingPayload())
            ->assertForbidden();
        $this->actingAs($this->educator())->getJson('/api/v1/professional/listings')->assertForbidden();
        $this->actingAs($owner)->postJson("/api/v1/professional/listings/{$listingId}/logo", ['file' => $this->pngUpload()])
            ->assertOk()->assertJsonPath('data.id', $listingId);
    }

    public function test_claim_approval_assigns_ownership_and_closes_competing_claims(): void
    {
        Notification::fake();
        $listing = ProfessionalListing::create([
            'professional_category_id' => $this->category()->id,
            'name' => 'Coastal Speech Therapy',
            'slug' => 'coastal-speech-therapy',
            'city' => 'Burnaby',
            'province' => 'British Columbia',
            'postal_code' => 'V5H 2A1',
        ]);
        $first = $this->professional();
        $second = $this->professional();

        $claimId = $this->actingAs($first)->postJson("/api/v1/professionals/{$listing->id}/claim", ['business_phone' => '604-555-0199'])
            ->assertCreated()->json('data.id');
        $this->actingAs($first)->postJson("/api/v1/professionals/{$listing->id}/claim")->assertStatus(422);
        $this->actingAs($second)->postJson("/api/v1/professionals/{$listing->id}/claim")->assertCreated();

        $admin = $this->administrator();
        $this->actingAs($admin)->getJson('/api/v1/admin/claims')->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.listing.name', 'Coastal Speech Therapy')
            ->assertJsonPath('data.0.has_document', false);

        $this->actingAs($admin)->postJson("/api/v1/admin/claims/{$claimId}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame($first->id, $listing->refresh()->owner_user_id);
        $this->assertSame('rejected', $second->claims()->first()->status);
        Notification::assertSentTo($first, ProfessionalListingReviewedNotification::class);

        $this->actingAs($admin)->postJson("/api/v1/admin/claims/{$claimId}/reject")->assertStatus(422);
        $this->actingAs($first)->getJson('/api/v1/professional/listings')->assertOk()
            ->assertJsonPath('data.listings.0.name', 'Coastal Speech Therapy')
            ->assertJsonPath('data.claims.0.status', 'approved');
    }

    public function test_moderation_is_administrator_only_and_credentials_expose_only_review_fields(): void
    {
        $educator = $this->educator();
        $this->actingAs($educator)->postJson('/api/v1/educator/credentials', ['kind' => 'certification', 'title' => 'ECE Certificate', 'issuer' => 'BC ECE Registry'])
            ->assertCreated();

        $this->actingAs($educator)->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->actingAs($this->professional())->getJson('/api/v1/admin/credentials')->assertForbidden();

        $response = $this->actingAs($this->administrator())->getJson('/api/v1/admin/credentials')->assertOk()
            ->assertJsonPath('data.0.title', 'ECE Certificate')
            ->assertJsonPath('data.0.educator.name', 'Priya Nair');
        $this->assertSame(['id', 'title', 'issuer', 'issued_on', 'expires_on', 'status', 'created_at', 'educator'], array_keys($response->json('data.0')));

        $this->actingAs($this->administrator())->putJson('/api/v1/admin/credentials/'.$response->json('data.0.id'), ['status' => 'verified'])
            ->assertOk()->assertJsonPath('data.status', 'verified');
        $this->assertTrue($educator->educatorProfile->refresh()->is_verified);
    }

    public function test_professional_renames_account_and_deactivation_hides_listings(): void
    {
        $owner = $this->professional();
        $this->actingAs($owner)->putJson('/api/v1/account', ['name' => 'Samira Rivera', 'phone' => null])
            ->assertOk()->assertJsonPath('data.name', 'Samira Rivera');
        $this->actingAs($this->educator())->putJson('/api/v1/account', ['name' => 'Nope'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        // Regression: centres save only their phone; name fields must not be demanded.
        $this->actingAs($this->centreOwner())->putJson('/api/v1/account', ['phone' => '604-555-0100'])
            ->assertOk()->assertJsonPath('data.phone', '604-555-0100');

        $listingId = $this->actingAs($owner)->postJson('/api/v1/professionals', $this->listingPayload())->json('data.id');
        $this->actingAs($this->administrator())->postJson("/api/v1/admin/listings/{$listingId}/approve")->assertOk();
        $this->getJson('/api/v1/professionals?keyword=Rivera')->assertJsonPath('meta.total', 1);

        $this->actingAs($owner)->postJson('/api/v1/account/deactivate', ['password' => self::PASSWORD, 'confirm' => true])->assertOk();
        $this->getJson('/api/v1/professionals?keyword=Rivera')->assertJsonPath('meta.total', 0);

        app(AuthService::class)->authenticate($owner->email, self::PASSWORD);
        $this->getJson('/api/v1/professionals?keyword=Rivera')->assertJsonPath('meta.total', 1);
    }

    private function professional(): User
    {
        $user = app(AuthService::class)->register([
            'role' => 'professional',
            'email' => 'pro'.uniqid().'@example.com',
            'password' => self::PASSWORD,
            'first_name' => 'Sam',
            'last_name' => 'Rivera',
            'city' => 'Vancouver',
            'province' => 'British Columbia',
            'postal_code' => 'V6B 1A1',
            'country' => 'Canada',
            'terms' => true,
        ]);
        $user->markEmailAsVerified();

        return $user->refresh();
    }

    private function administrator(): User
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin'.uniqid().'@example.com', 'password' => self::PASSWORD, 'role' => 'administrator', 'status' => 'active']);
        Role::findOrCreate('administrator', 'web');
        $user->assignRole('administrator');
        $user->markEmailAsVerified();

        return $user;
    }

    private function category(): ProfessionalCategory
    {
        return ProfessionalCategory::firstOrCreate(['slug' => 'speech-language-pathologists'], ['name' => 'Speech-Language Pathologists', 'is_active' => true]);
    }

    private function listingPayload(array $overrides = []): array
    {
        return array_merge([
            'professional_category_id' => $this->category()->id,
            'name' => 'Rivera Speech & Language',
            'professional_title' => 'Registered Speech-Language Pathologist',
            'description' => 'Play-based speech and language support for children 2–6, in your centre.',
            'services_offered' => ['In-centre screening', 'Educator workshops'],
            'city' => 'Vancouver',
            'province' => 'British Columbia',
            'postal_code' => 'V6B 1A1',
            'email' => 'hello@rivera.example',
            'website' => 'https://rivera.example',
        ], $overrides);
    }
}
