<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EducatorPublicResource;
use App\Models\EducatorProfile;
use App\Models\JobApplication;
use App\Models\User;
use App\Notifications\ProfileViewedNotification;
use App\Services\ProfileViewRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * GET /educators/{slug}. Visibility is enforced here, never only in the UI:
 *  - public:            anyone
 *  - verified_centres:  verified centres, plus centres the educator applied to
 *  - applied_only:      only centres the educator applied to
 * The owner can always preview their own profile.
 */
class EducatorPublicController extends Controller
{
    public function show(Request $request, string $slug, ProfileViewRecorder $views): JsonResponse
    {
        $profile = EducatorProfile::query()->where('slug', $slug)->with(['credentials', 'user'])->firstOrFail();
        $viewer = $request->user('sanctum');

        abort_unless($profile->user?->status === 'active' || $this->isOwner($profile, $viewer), 404, 'This profile is not available.');
        abort_unless($this->canView($profile, $viewer), 404, 'This profile is not available.');

        if ($viewer?->isCentre() && $centre = $viewer->centre) {
            $views->record($profile, $viewer, (string) $viewer->id);
            if (Cache::add("profile-view-notified:{$profile->id}:{$centre->id}:".now()->toDateString(), true, now()->endOfDay())) {
                $profile->user->notify(new ProfileViewedNotification($centre));
            }
        }

        return $this->ok(new EducatorPublicResource($profile));
    }

    private function canView(EducatorProfile $profile, ?User $viewer): bool
    {
        if ($this->isOwner($profile, $viewer) || $viewer?->isAdministrator()) {
            return true;
        }

        $appliedToViewer = $viewer?->isCentre() && JobApplication::query()
            ->where('educator_id', $profile->user_id)
            ->whereHas('job', fn ($q) => $q->whereIn('childcare_centre_id', $viewer->childcareCentres()->pluck('id')))
            ->exists();

        return match ($profile->visibility) {
            EducatorProfile::VISIBILITY_PUBLIC => true,
            EducatorProfile::VISIBILITY_VERIFIED_CENTRES => $appliedToViewer
                || ($viewer?->isCentre() && $viewer->childcareCentres()->where('is_verified', true)->exists()),
            default => (bool) $appliedToViewer,
        };
    }

    private function isOwner(EducatorProfile $profile, ?User $viewer): bool
    {
        return $viewer !== null && (int) $viewer->id === (int) $profile->user_id;
    }
}
