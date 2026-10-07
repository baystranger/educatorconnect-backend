<?php

namespace App\Http\Controllers\Api\V1\Centre;

use App\Http\Concerns\ResolvesCentre;
use App\Http\Controllers\Controller;
use App\Http\Requests\Centre\GalleryItemRequest;
use App\Http\Requests\Centre\GalleryUploadRequest;
use App\Http\Requests\MediaUploadRequest;
use App\Http\Resources\CentreOwnerResource;
use App\Models\CentreGalleryItem;
use App\Services\CentreProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Photo gallery and parent resources (e.g. handbook PDF) for the owner's centre.
 */
class GalleryController extends Controller
{
    use ResolvesCentre;

    public function __construct(private readonly CentreProfileService $profiles) {}

    public function store(GalleryUploadRequest $request): JsonResponse
    {
        $centre = $this->centre($request);
        $this->profiles->addPhotos($centre, $request->file('files'), $request->validated('category', 'classrooms'));

        return $this->created(new CentreOwnerResource($this->profiles->load($centre->refresh())), 'Photos added.');
    }

    public function update(GalleryItemRequest $request, int $item): JsonResponse
    {
        $centre = $this->centre($request);
        $centre->gallery()->findOrFail($item)->update($request->validated());

        return $this->ok(new CentreOwnerResource($this->profiles->load($centre->refresh())), 'Photo updated.');
    }

    public function cover(Request $request, int $item): JsonResponse
    {
        $centre = $this->centre($request);
        $this->profiles->setCover($centre, $centre->gallery()->findOrFail($item));

        return $this->ok(new CentreOwnerResource($this->profiles->load($centre->refresh())), 'Cover photo set.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $centre = $this->centre($request);
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        foreach (array_values($ids) as $index => $id) {
            CentreGalleryItem::query()->where('childcare_centre_id', $centre->id)->whereKey($id)->update(['sort_order' => $index]);
        }

        return $this->ok(new CentreOwnerResource($this->profiles->load($centre->refresh())), 'Order saved.');
    }

    public function destroy(Request $request, int $item): JsonResponse
    {
        $centre = $this->centre($request);
        $this->profiles->deletePhoto($centre->gallery()->findOrFail($item));

        return $this->ok(new CentreOwnerResource($this->profiles->load($centre->refresh())), 'Photo deleted.');
    }

    public function storeResource(MediaUploadRequest $request): JsonResponse
    {
        $centre = $this->centre($request);
        $request->validate(['title' => ['required', 'string', 'max:150']]);
        $this->profiles->addResource($centre, $request->file('file'), $request->input('title'), $request->input('description'));

        return $this->created(new CentreOwnerResource($this->profiles->load($centre->refresh())), 'Document uploaded.');
    }

    public function destroyResource(Request $request, int $resource): JsonResponse
    {
        $centre = $this->centre($request);
        $model = $centre->resources()->findOrFail($resource);
        app(\App\Services\MediaStorage::class)->deletePublic($model->file_path);
        $model->delete();

        return $this->ok(new CentreOwnerResource($this->profiles->load($centre->refresh())), 'Document removed.');
    }
}
