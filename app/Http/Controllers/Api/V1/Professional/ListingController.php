<?php

namespace App\Http\Controllers\Api\V1\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\MediaUploadRequest;
use App\Http\Requests\ProfessionalListingRequest;
use App\Http\Resources\ProfessionalListingOwnerResource;
use App\Models\ProfessionalListing;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A professional's own directory listings and claims. Guarded by role:professional;
 * ownership is checked per listing.
 */
class ListingController extends Controller
{
    public function __construct(private readonly MediaStorage $media) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $listings = $user->professionalListings()->with('category:id,name,slug')->latest()->get();
        $claims = $user->claims()->with('listing:id,name,slug,city')->latest()->get()->map(fn ($claim) => [
            'id' => $claim->id,
            'status' => $claim->status,
            'listing' => $claim->listing?->only(['id', 'name', 'slug', 'city']),
            'admin_notes' => $claim->status === 'rejected' ? $claim->admin_notes : null,
            'created_at' => $claim->created_at?->toIso8601String(),
            'reviewed_at' => $claim->reviewed_at?->toIso8601String(),
        ]);

        return $this->ok([
            'listings' => ProfessionalListingOwnerResource::collection($listings)->resolve($request),
            'claims' => $claims,
        ]);
    }

    public function update(ProfessionalListingRequest $request, ProfessionalListing $listing): JsonResponse
    {
        $this->ensureOwner($request, $listing);

        $listing->fill($request->validated());
        // A listing sent back for changes goes back into the review queue once edited.
        if ($listing->status === 'rejected') {
            $listing->forceFill(['status' => 'pending_review', 'review_notes' => null]);
        }
        $listing->save();

        $message = $listing->status === 'pending_review' ? 'Saved. Your listing is in the review queue.' : 'Listing updated.';

        return $this->ok(new ProfessionalListingOwnerResource($listing->load('category:id,name,slug')), $message);
    }

    public function uploadLogo(MediaUploadRequest $request, ProfessionalListing $listing): JsonResponse
    {
        $this->ensureOwner($request, $listing);

        $previous = $listing->photo_or_logo_path;
        $listing->forceFill(['photo_or_logo_path' => $this->media->storePublic($request->file('file'), 'professionals')])->save();
        $this->media->deletePublic($previous);

        return $this->ok(new ProfessionalListingOwnerResource($listing->load('category:id,name,slug')), 'Logo updated.');
    }

    private function ensureOwner(Request $request, ProfessionalListing $listing): void
    {
        abort_unless($listing->owner_user_id === $request->user()->id, 403, 'You can only manage your own listings.');
    }
}
