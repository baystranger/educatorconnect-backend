<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChildcareCentre;
use App\Models\EducatorCredential;
use App\Models\ProfessionalClaim;
use App\Services\AdminModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Administrator moderation queue. Guarded by role:administrator.
 */
class ModerationController extends Controller
{
    public function __construct(private readonly AdminModerationService $moderation) {}

    public function centres(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['draft', 'pending_review', 'published', 'suspended'])]]);

        $centres = ChildcareCentre::query()
            ->when($data['status'] ?? 'pending_review', fn ($q, $status) => $q->where('status', $status))
            ->with('owner:id,name,email')
            ->latest('submitted_at')
            ->paginate(25);

        return $this->ok($centres->through(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'city' => $c->city,
            'status' => $c->status,
            'is_verified' => (bool) $c->is_verified,
            'licence_type' => $c->licence_type,
            'licence_number' => $c->licence_number,
            'submitted_at' => $c->submitted_at?->toIso8601String(),
            'owner' => $c->owner?->only(['id', 'name', 'email']),
        ]));
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

        return $this->ok($centre->only(['id', 'name', 'status', 'is_published', 'is_verified']), 'Centre updated.');
    }

    public function credentials(): JsonResponse
    {
        $credentials = EducatorCredential::query()
            ->where('kind', 'certification')
            ->where('verification_status', 'pending')
            ->with('profile:id,first_name,last_name,slug')
            ->oldest()
            ->paginate(25);

        return $this->ok($credentials);
    }

    public function verifyCredential(Request $request, EducatorCredential $credential): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'rejected', 'pending'])]]);

        return $this->ok($this->moderation->verifyCredential($credential, $data['status']), 'Credential updated.');
    }

    public function claims(): JsonResponse
    {
        return $this->ok(ProfessionalClaim::query()->where('status', 'pending')->with(['listing:id,name,slug', 'user:id,name,email'])->oldest()->paginate(25));
    }

    public function claimAction(Request $request, ProfessionalClaim $claim, string $action): JsonResponse
    {
        $notes = $request->validate(['notes' => ['nullable', 'string', 'max:2000']])['notes'] ?? '';
        $claim = match ($action) {
            'approve' => $this->moderation->approveClaim($claim, $request->user()->id),
            'reject' => $this->moderation->rejectClaim($claim, $request->user()->id, $notes),
            default => abort(404),
        };

        return $this->ok($claim, 'Claim updated.');
    }
}
