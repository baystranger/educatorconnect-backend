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
    public function approveCentre(ChildcareCentre $centre): ChildcareCentre
    {
        $wasLive = $centre->isPubliclyVisible();
        $centre->forceFill([
            'status' => 'published',
            'is_published' => true,
            'published_at' => $centre->published_at ?? now(),
        ])->save();

        if (! $wasLive) {
            $centre->owner->notify(new CentreProfilePublishedNotification($centre));
        }

        return $centre->refresh();
    }

    public function verifyCentre(ChildcareCentre $centre, bool $verified): ChildcareCentre
    {
        $centre->forceFill([
            'is_verified' => $verified,
            'licensing_status' => $verified ? 'verified' : null,
        ])->save();

        return $centre->refresh();
    }

    public function suspendCentre(ChildcareCentre $centre): ChildcareCentre
    {
        $centre->forceFill(['status' => 'suspended', 'is_published' => false])->save();

        return $centre->refresh();
    }

    public function verifyCredential(EducatorCredential $credential, string $status): EducatorCredential
    {
        $credential->forceFill(['verification_status' => $status])->save();

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

            return $claim->refresh();
        });

        $claim->user->notify(new ProfessionalListingReviewedNotification($claim->listing, true));

        return $claim;
    }

    public function approveListing(ProfessionalListing $listing, int $adminId): ProfessionalListing
    {
        $listing->forceFill([
            'status' => 'claimed_verified',
            'is_verified' => true,
            'review_notes' => null,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ])->save();

        $listing->owner?->notify(new ProfessionalListingReviewedNotification($listing, true));

        return $listing->refresh();
    }

    public function rejectListing(ProfessionalListing $listing, int $adminId, string $notes): ProfessionalListing
    {
        $listing->forceFill([
            'status' => 'rejected',
            'review_notes' => $notes,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ])->save();

        $listing->owner?->notify(new ProfessionalListingReviewedNotification($listing, false, $notes));

        return $listing->refresh();
    }

    public function rejectClaim(ProfessionalClaim $claim, int $adminId, string $notes = ''): ProfessionalClaim
    {
        $claim->forceFill(['status' => 'rejected', 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'admin_notes' => $notes])->save();

        return $claim->refresh();
    }
}
