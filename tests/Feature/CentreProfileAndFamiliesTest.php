<?php

namespace Tests\Feature;

use App\Notifications\ParentReplyNotification;
use App\Notifications\ParentRequestConfirmationNotification;
use App\Notifications\ParentRequestReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CentreProfileAndFamiliesTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_builder_sections_completion_and_submit(): void
    {
        Storage::fake('public');
        $owner = $this->centreOwner(published: false);

        $this->actingAs($owner)->putJson('/api/v1/centre/profile/basics', [
            'name' => 'Little Sprouts Daycare',
            'contact_person' => 'Anita Sharma',
            'phone' => '(604) 555-0100',
            'email' => 'hello@littlesprouts.ca',
            'website' => 'littlesprouts.ca',
            'address' => '1420 Kingsway',
            'city' => 'Burnaby',
            'postal_code' => 'V5H 2A1',
            'province' => 'British Columbia',
            'country' => 'Canada',
            'hours_of_operation' => [
                ['label' => 'Mon – Fri', 'value' => '7:30 AM – 5:30 PM'],
                ['label' => 'Sat – Sun', 'value' => 'Closed'],
            ],
            'licence_type' => 'group_child_care',
            'licence_number' => 'LIC-12345',
        ])->assertOk()
            ->assertJsonPath('data.completion.steps.basics.complete', true)
            ->assertJsonPath('data.licence_number', 'LIC-12345');

        $this->actingAs($owner)->putJson('/api/v1/centre/profile/about', [
            'about' => 'Family-run since 2014.',
            'philosophy' => 'Children learn through play.',
            'teaching_approaches' => ['play_based', 'emergent'],
            'daily_schedule' => [['time' => '7:30 AM', 'activity' => 'Arrival & free play']],
        ])->assertOk()->assertJsonPath('data.completion.steps.about.complete', true);

        $programs = $this->actingAs($owner)->putJson('/api/v1/centre/profile/programs', [
            'programs' => [
                ['name' => 'Infant', 'min_age_months' => 6, 'max_age_months' => 18, 'availability' => 'waitlist', 'schedule' => 'full_day', 'tuition_amount' => 650],
                ['name' => 'Toddler', 'min_age_months' => 18, 'max_age_months' => 36, 'availability' => 'spaces', 'schedule' => 'full_day', 'tuition_amount' => 500],
            ],
        ])->assertOk()
            ->assertJsonPath('data.age_range', '6 mo–3 yrs')
            ->assertJsonPath('data.availability.label', 'Toddler spaces');

        // Resubmitting keeps ids (update) and drops omitted rows.
        $toddlerId = collect($programs->json('data.form.programs'))->firstWhere('name', 'Toddler')['id'];
        $this->actingAs($owner)->putJson('/api/v1/centre/profile/programs', [
            'programs' => [['id' => $toddlerId, 'name' => 'Toddler', 'min_age_months' => 18, 'max_age_months' => 36, 'availability' => 'full']],
        ])->assertOk()->assertJsonCount(1, 'data.form.programs')->assertJsonPath('data.form.programs.0.id', $toddlerId);

        $this->actingAs($owner)->postJson('/api/v1/centre/gallery', [
            'files' => [$this->pngUpload('room.png'), $this->pngUpload('garden.png')],
            'category' => 'outdoor_play',
        ])->assertCreated()
            ->assertJsonCount(2, 'data.gallery')
            ->assertJsonPath('data.gallery.0.is_cover', true);

        $this->actingAs($owner)->postJson('/api/v1/centre/gallery', ['files' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]])
            ->assertUnprocessable();

        $this->actingAs($owner)->postJson('/api/v1/centre/profile/submit')->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->actingAs($owner)->postJson('/api/v1/centre/profile/submit', ['confirm' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        // Public profile hides the licence number.
        $public = $this->getJson('/api/v1/childcare-centres/'.$owner->centre->slug)->assertOk();
        $this->assertArrayNotHasKey('licence_number', $public->json('data'));
        $this->assertSame('Mon–Fri · 7:30–5:30', $public->json('data.hours'));
    }

    public function test_draft_centres_are_not_public_and_search_filters_work(): void
    {
        $draft = $this->centreOwner(['centre_name' => 'Hidden Draft Centre'], published: false);
        $live = $this->centreOwner();
        $this->centreOf($live)->programs()->create(['name' => 'Toddler', 'min_age_months' => 18, 'max_age_months' => 36, 'availability' => 'spaces']);

        $this->getJson('/api/v1/childcare-centres/'.$draft->centre->slug)->assertNotFound();

        $this->getJson('/api/v1/childcare-centres?latitude=49.25&longitude=-123.0&radius=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Little Sprouts Daycare')
            ->assertJsonPath('data.0.availability.label', 'Toddler spaces');

        $this->getJson('/api/v1/childcare-centres?age_group=toddler&spaces_available=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/childcare-centres?age_group=infant')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/childcare-centres?location=Burnaby')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/childcare-centres?radius=7')->assertUnprocessable()->assertJsonPath('success', false);
    }

    public function test_parent_can_contact_centre_and_centre_replies(): void
    {
        Notification::fake();
        $owner = $this->centreOwner();
        $slug = $owner->centre->slug;

        $this->postJson("/api/v1/childcare-centres/{$slug}/requests", [
            'type' => 'tour', 'parent_name' => 'Chen family', 'email' => 'chen@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors(['requested_at', 'consent']);

        $this->postJson("/api/v1/childcare-centres/{$slug}/requests", [
            'type' => 'inquiry', 'parent_name' => 'Amira M.', 'email' => 'amira@example.com', 'consent' => true, 'website' => 'spam',
        ])->assertUnprocessable();

        $this->postJson("/api/v1/childcare-centres/{$slug}/requests", [
            'type' => 'inquiry',
            'parent_name' => 'Amira M.',
            'email' => 'Amira@Example.com',
            'phone' => '(604) 555-0144',
            'child_age' => 'Under 1 year',
            'program_of_interest' => 'Infant',
            'preferred_contact_method' => 'email',
            'message' => 'Do you expect any infant spaces opening in January?',
            'consent' => true,
        ])->assertCreated()->assertJsonPath('success', true);

        Notification::assertSentTo($owner, ParentRequestReceivedNotification::class);
        Notification::assertSentTo(new AnonymousNotifiable, ParentRequestConfirmationNotification::class);

        $list = $this->actingAs($owner)->getJson('/api/v1/centre/families?type=inquiry')
            ->assertOk()
            ->assertJsonPath('meta.counts.inquiry', 1)
            ->assertJsonPath('data.0.email', 'amira@example.com')
            ->assertJsonPath('data.0.has_consent', true);
        $id = $list->json('data.0.id');

        $this->actingAs($owner)->getJson('/api/v1/centre/dashboard')
            ->assertOk()->assertJsonPath('data.attention.counts.families', 1)->assertJsonPath('data.stats.unanswered_inquiries', 1);

        $this->actingAs($owner)->postJson("/api/v1/centre/families/{$id}/reply", ['body' => 'Yes — we expect two infant spaces in January.'])
            ->assertCreated();
        Notification::assertSentTo(new AnonymousNotifiable, ParentReplyNotification::class);

        $this->actingAs($owner)->getJson("/api/v1/centre/families/{$id}")
            ->assertOk()->assertJsonPath('data.status', 'replied')->assertJsonCount(1, 'data.messages');

        $other = $this->centreOwner(['centre_name' => 'Other Centre']);
        $this->actingAs($other)->getJson("/api/v1/centre/families/{$id}")->assertForbidden();
    }
}
