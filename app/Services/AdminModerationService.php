<?php

namespace App\Services;

use App\Models\ChildcareCentre;
use App\Models\EducatorCredential;
use App\Models\ProfessionalClaim;
use App\Models\ProfessionalListing;
use App\Notifications\CentreProfilePublishedNotification;
use App\Notifications\ProfessionalListingReviewedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Administrator actions. Moderation-only attributes are written with forceFill because
 * they are intentionally not mass-assignable.
 */
class AdminModerationService
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function approveCentre(ChildcareCentre $centre): ChildcareCentre
    {
        $wasLive = $centre->isPubliclyVisible();
        $before = $centre->only(['status', 'is_published']);
        $centre->forceFill([
            'status' => 'published',
            'is_published' => true,
            'published_at' => $centre->published_at ?? now(),
        ])->save();
        $this->audit->recordChange('centre.approved', $centre, $before, $centre->only(['status', 'is_published']));

        if (! $wasLive) {
            $centre->owner->notify(new CentreProfilePublishedNotification($centre));
        }

        return $centre->refresh();
    }

    public function verifyCentre(ChildcareCentre $centre, bool $verified): ChildcareCentre
    {
        $before = $centre->only(['is_verified']);
        $centre->forceFill([
            'is_verified' => $verified,
            'licensing_status' => $verified ? 'verified' : null,
        ])->save();
        $this->audit->recordChange($verified ? 'centre.verified' : 'centre.unverified', $centre, $before, $centre->only(['is_verified']));

        return $centre->refresh();
    }

    public function suspendCentre(ChildcareCentre $centre): ChildcareCentre
    {
        $before = $centre->only(['status', 'is_published']);
        $centre->forceFill(['status' => 'suspended', 'is_published' => false])->save();
        $this->audit->recordChange('centre.suspended', $centre, $before, $centre->only(['status', 'is_published']));

        return $centre->refresh();
    }

    public function verifyCredential(EducatorCredential $credential, string $status): EducatorCredential
    {
        $before = $credential->verification_status;
        $credential->forceFill(['verification_status' => $status])->save();
        $this->audit->recordChange('credential.reviewed', $credential, ['verification_status' => $before], ['verification_status' => $status]);

        // An educator is "verified" once at least one certification is verified.
        $profile = $credential->profile;
        $profile->forceFill([
            'is_verified' => $profile->credentials()->where('kind', 'certification')->where('verification_status', 'verified')->exists(),
        ])->save();

        return $credential->refresh();
    }

    public function approveClaim(ProfessionalClaim $claim, int $adminId): ProfessionalClaim
    {
        $claim = DB::transaction(function () use ($claim, $adminId): ProfessionalClaim {
            $claim->forceFill(['status' => 'approved', 'reviewed_by' => $adminId, 'reviewed_at' => now()])->save();
            $claim->listing->forceFill([
                'owner_user_id' => $claim->user_id,
                'status' => 'claimed_verified',
                'is_verified' => true,
            ])->save();

            // A listing has one owner: competing claims are closed.
            $claim->listing->claims()->whereKeyNot($claim->id)->where('status', 'pending')->update([
                'status' => 'rejected',
                'admin_notes' => 'Another claim for this listing was approved.',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
            ]);

            $this->audit->record('claim.approved', $claim, ['status' => 'pending'], ['status' => 'approved', 'listing_id' => $claim->professional_listing_id, 'owner_user_id' => $claim->user_id]);

            return $claim->refresh();
        });

        $claim->user->notify(new ProfessionalListingReviewedNotification($claim->listing, true));

        return $claim;
    }

    public function approveListing(ProfessionalListing $listing, int $adminId): ProfessionalListing
    {
        $before = $listing->status;
        $listing->forceFill([
            'status' => 'claimed_verified',
            'is_verified' => true,
            'review_notes' => null,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ])->save();
        $this->audit->record('listing.approved', $listing, ['status' => $before], ['status' => 'claimed_verified']);

        $listing->owner?->notify(new ProfessionalListingReviewedNotification($listing, true));

        return $listing->refresh();
    }

    public function rejectListing(ProfessionalListing $listing, int $adminId, string $notes): ProfessionalListing
    {
        $before = $listing->status;
        $listing->forceFill([
            'status' => 'rejected',
            'review_notes' => $notes,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ])->save();
        $this->audit->record('listing.rejected', $listing, ['status' => $before], ['status' => 'rejected', 'notes' => $notes]);

        $listing->owner?->notify(new ProfessionalListingReviewedNotification($listing, false, $notes));

        return $listing->refresh();
    }

    public function rejectClaim(ProfessionalClaim $claim, int $adminId, string $notes = ''): ProfessionalClaim
    {
        $claim->forceFill(['status' => 'rejected', 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'admin_notes' => $notes])->save();
        $this->audit->record('claim.rejected', $claim, ['status' => 'pending'], ['status' => 'rejected', 'notes' => $notes]);

        return $claim->refresh();
    }
}
