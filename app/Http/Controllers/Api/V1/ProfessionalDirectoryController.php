<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalClaimRequest;
use App\Http\Requests\ProfessionalListingRequest;
use App\Models\ProfessionalCategory;
use App\Models\ProfessionalListing;
use App\Services\MediaStorage;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Early childhood professional directory (therapists, consultants, services for centres).
 */
class ProfessionalDirectoryController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Cache::remember('professional-categories', now()->addHour(), fn () => ProfessionalCategory::query()
            ->where('is_active', true)
            ->withCount(['listings' => fn (Builder $q) => $q->whereIn('status', ['unclaimed', 'claimed_verified'])])
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->toArray());

        return $this->ok($categories);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'featured' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = ProfessionalListing::query()->with('category:id,name,slug')
            ->whereIn('status', ['unclaimed', 'claimed_verified']);

        if (! empty($filters['keyword'])) {
            $keyword = '%'.$filters['keyword'].'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $keyword)
                ->orWhere('professional_title', 'like', $keyword)
                ->orWhere('description', 'like', $keyword)
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $keyword)));
        }
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $c) => $c->where('slug', $filters['category']));
        }
        if (! empty($filters['location'])) {
            $location = '%'.trim(explode(',', $filters['location'])[0]).'%';
            $query->where(fn (Builder $q) => $q->where('city', 'like', $location)->orWhere('service_area', 'like', $location));
        }
        if (! empty($filters['featured'])) {
            $query->where('is_featured', true);
        }

        $results = $query->orderByDesc('is_featured')->orderByDesc('is_verified')->orderBy('name')
            ->paginate($filters['per_page'] ?? 12)->withQueryString();

        return $this->ok($results->through(fn ($listing) => $this->present($listing)));
    }

    public function show(string $slug): JsonResponse
    {
        $listing = ProfessionalListing::with('category:id,name,slug')
            ->where('slug', $slug)
            ->whereIn('status', ['unclaimed', 'claimed_verified'])
            ->firstOrFail();
        ProfessionalListing::query()->whereKey($listing->id)->increment('profile_views');

        return $this->ok($this->present($listing, true));
    }

    public function store(ProfessionalListingRequest $request): JsonResponse
    {
        $listing = new ProfessionalListing($request->validated());
        $listing->forceFill([
            'owner_user_id' => $request->user()->id,
            'slug' => Str::slug($listing->name).'-'.Str::lower(Str::random(6)),
            'status' => 'pending_review',
        ])->save();

        return $this->created($this->present($listing->load('category')), 'Professional profile submitted for review.');
    }

    public function claim(ProfessionalClaimRequest $request, ProfessionalListing $listing): JsonResponse
    {
        abort_if($listing->owner_user_id !== null, 422, 'This profile is already claimed.');
        abort_if($listing->claims()->where('user_id', $request->user()->id)->where('status', 'pending')->exists(), 422, 'You already have a pending claim for this profile.');

        $data = $request->validated();
        if (isset($data['supporting_document'])) {
            $data['supporting_document_path'] = app(MediaStorage::class)->storePrivate($data['supporting_document'], 'professional-claims');
        }
        unset($data['supporting_document']);

        $claim = $listing->claims()->make($data);
        $claim->forceFill(['user_id' => $request->user()->id, 'status' => 'pending'])->save();

        return $this->created(['id' => $claim->id, 'status' => $claim->status], 'Claim submitted for administrator review.');
    }

    private function present(ProfessionalListing $listing, bool $detailed = false): array
    {
        $status = match (true) {
            $listing->status === 'unclaimed' => 'unclaimed',
            (bool) $listing->is_verified => 'verified',
            default => 'claimed',
        };

        $data = [
            'id' => $listing->id,
            'slug' => $listing->slug,
            'name' => $listing->name,
            'initials' => Format::initials($listing->name),
            'professional_title' => $listing->professional_title,
            'category' => $listing->category?->only(['name', 'slug']),
            'city' => $listing->city,
            'service_area' => $listing->service_area,
            'summary' => Str::limit((string) $listing->description, 110),
            'listing_status' => $status,
            'is_featured' => (bool) $listing->is_featured,
            'book_consultation' => (bool) $listing->book_consultation,
            'logo_url' => app(MediaStorage::class)->url($listing->photo_or_logo_path),
        ];

        if ($detailed) {
            $data += [
                'description' => $listing->description,
                'services_offered' => $listing->services_offered ?? [],
                'credentials' => $listing->credentials ?? [],
                'website' => $listing->website,
                'email' => $listing->owner_user_id ? $listing->email : null,
                'phone' => $listing->owner_user_id ? $listing->phone : null,
                'social_links' => $listing->social_links ?? [],
            ];
        }

        return $data;
    }
}
