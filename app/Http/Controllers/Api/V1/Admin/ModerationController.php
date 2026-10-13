<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfessionalListingOwnerResource;
use App\Models\ChildcareCentre;
use App\Models\EducatorCredential;
use App\Models\ProfessionalClaim;
use App\Models\ProfessionalListing;
use App\Services\AdminModerationService;
use App\Services\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administrator moderation queue. Guarded by role:administrator.
 */
class ModerationController extends Controller
{
    public function __construct(private readonly AdminModerationService $moderation) {}

    /** Queue sizes for the console tabs. */
    public function overview(): JsonResponse
    {
        return $this->ok([
            'centres_pending' => ChildcareCentre::query()->where('status', 'pending_review')->count(),
            'credentials_pending' => EducatorCredential::query()->where('kind', 'certification')->where('verification_status', 'pending')->count(),
            'claims_pending' => ProfessionalClaim::query()->where('status', 'pending')->count(),
            'listings_pending' => ProfessionalListing::query()->where('status', 'pending_review')->count(),
        ]);
    }

    public function centres(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'pending_review', 'published', 'suspended'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $centres = ChildcareCentre::query()
            ->where('status', $data['status'] ?? 'pending_review')
            ->when($data['q'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('city', 'like', "%{$term}%")->orWhere('licence_number', 'like', "%{$term}%")))
            ->with('owner:id,name,email')
            ->latest('submitted_at')->latest('id')
            ->paginate(25);

        return $this->ok($centres->through(fn (ChildcareCentre $c) => $this->presentCentre($c)));
    }

    public function centreAction(Request $request, ChildcareCentre $centre, string $action): JsonResponse
    {
        $centre = match ($action) {
            'approve' => $this->moderation->approveCentre($centre),
            'verify' => $this->moderation->verifyCentre($centre, true),
            'unverify' => $this->moderation->verifyCentre($centre, false),
            'suspend' => $this->moderation->suspendCentre($centre),
            default => abort(404),
        };

        return $this->ok($this->presentCentre($centre->load('owner:id,name,email')), 'Centre updated.');
    }

    public function credentials(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'verified', 'rejected'])]]);

        $credentials = EducatorCredential::query()
            ->where('kind', 'certification')
            ->where('verification_status', $data['status'] ?? 'pending')
            ->with('profile:id,user_id,first_name,last_name,slug,city,province')
            ->oldest()
            ->paginate(25);

        return $this->ok($credentials->through(fn (EducatorCredential $c) => $this->presentCredential($c)));
    }

    public function verifyCredential(Request $request, EducatorCredential $credential): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'rejected', 'pending'])]]);
        $credential = $this->moderation->verifyCredential($credential, $data['status']);

        return $this->ok($this->presentCredential($credential->load('profile:id,user_id,first_name,last_name,slug,city,province')), 'Credential updated.');
    }

    public function claims(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]]);

        $claims = ProfessionalClaim::query()
            ->where('status', $data['status'] ?? 'pending')
            ->with(['listing:id,name,slug,city,email,website', 'user:id,name,email'])
            ->oldest()
            ->paginate(25);

        return $this->ok($claims->through(fn (ProfessionalClaim $claim) => $this->presentClaim($claim)));
    }

    public function claimDocument(ProfessionalClaim $claim, MediaStorage $media): StreamedResponse
    {
        $path = $claim->supporting_document_path;
        abort_unless($path && Storage::disk($media->privateDisk())->exists($path), 404, 'This claim has no supporting document.');

        return Storage::disk($media->privateDisk())->download($path, 'claim-'.$claim->id.'.'.pathinfo($path, PATHINFO_EXTENSION));
    }

    public function claimAction(Request $request, ProfessionalClaim $claim, string $action): JsonResponse
    {
        abort_if($claim->status !== 'pending', 422, 'This claim has already been reviewed.');
        $notes = $request->validate(['notes' => ['nullable', 'string', 'max:2000']])['notes'] ?? '';

        $claim = match ($action) {
            'approve' => $this->moderation->approveClaim($claim, $request->user()->id),
            'reject' => $this->moderation->rejectClaim($claim, $request->user()->id, $notes),
            default => abort(404),
        };

        return $this->ok($this->presentClaim($claim->load(['listing:id,name,slug,city,email,website', 'user:id,name,email'])), 'Claim updated.');
    }

    public function listings(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending_review', 'claimed_verified', 'rejected', 'unclaimed'])]]);

        $listings = ProfessionalListing::query()
            ->where('status', $data['status'] ?? 'pending_review')
            ->with(['category:id,name,slug', 'owner:id,name,email'])
            ->oldest()
            ->paginate(25);

        return $this->ok($listings->through(fn (ProfessionalListing $listing) => (new ProfessionalListingOwnerResource($listing))->resolve($request)));
    }

    public function listingAction(Request $request, ProfessionalListing $listing, string $action): JsonResponse
    {
        $data = $request->validate(['notes' => [Rule::requiredIf($action === 'reject'), 'nullable', 'string', 'max:2000']]);

        $listing = match ($action) {
            'approve' => $this->moderation->approveListing($listing, $request->user()->id),
            'reject' => $this->moderation->rejectListing($listing, $request->user()->id, $data['notes']),
            default => abort(404),
        };

        return $this->ok(new ProfessionalListingOwnerResource($listing->load(['category:id,name,slug', 'owner:id,name,email'])), 'Listing updated.');
    }

    private function presentCentre(ChildcareCentre $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'city' => $c->city,
            'province' => $c->province,
            'status' => $c->status,
            'is_published' => (bool) $c->is_published,
            'is_verified' => (bool) $c->is_verified,
            'licence_type' => $c->licence_type,
            'licence_number' => $c->licence_number,
            'submitted_at' => $c->submitted_at?->toIso8601String(),
            'owner' => $c->owner?->only(['id', 'name', 'email']),
        ];
    }

    private function presentCredential(EducatorCredential $c): array
    {
        $profile = $c->profile;

        return [
            'id' => $c->id,
            'title' => $c->title,
            'issuer' => $c->issuer,
            'issued_on' => $c->issued_on?->toDateString(),
            'expires_on' => $c->expires_on?->toDateString(),
            'status' => $c->verification_status,
            'created_at' => $c->created_at?->toIso8601String(),
            'educator' => $profile ? [
                'name' => trim($profile->first_name.' '.$profile->last_name),
                'slug' => $profile->slug,
                'location' => implode(', ', array_filter([$profile->city, $profile->province])),
            ] : null,
        ];
    }

    private function presentClaim(ProfessionalClaim $claim): array
    {
        return [
            'id' => $claim->id,
            'status' => $claim->status,
            'business_domain_email' => $claim->business_domain_email,
            'business_phone' => $claim->business_phone,
            'credential_information' => $claim->credential_information,
            'has_document' => (bool) $claim->supporting_document_path,
            'admin_notes' => $claim->admin_notes,
            'created_at' => $claim->created_at?->toIso8601String(),
            'listing' => $claim->listing?->only(['id', 'name', 'slug', 'city', 'email', 'website']),
            'user' => $claim->user?->only(['id', 'name', 'email']),
        ];
    }
}
