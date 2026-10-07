<?php

namespace App\Http\Controllers\Api\V1\Centre;

use App\Http\Concerns\ResolvesCentre;
use App\Http\Controllers\Controller;
use App\Http\Requests\Centre\ParentRequestStatusRequest;
use App\Http\Requests\MessageRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\ParentRequestResource;
use App\Models\ParentRequest;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Families": inquiries, tour requests and waitlist sign-ups from the public profile.
 */
class FamilyController extends Controller
{
    use ResolvesCentre;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['nullable', Rule::in(ParentRequest::TYPES)]]);
        $centre = $this->centre($request);
        $type = $data['type'] ?? 'inquiry';

        $requests = $centre->parentRequests()
            ->where('type', $type)
            ->orderByRaw("CASE status WHEN 'new' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(30);

        $counts = $centre->parentRequests()
            ->whereNotIn('status', ['closed', 'declined'])
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return $this->ok(ParentRequestResource::collection($requests), meta: [
            'counts' => collect(ParentRequest::TYPES)->mapWithKeys(fn ($t) => [$t => (int) ($counts[$t] ?? 0)]),
        ]);
    }

    public function show(Request $request, ParentRequest $parentRequest): JsonResponse
    {
        $this->authorize('manage', $parentRequest);

        return $this->ok(new ParentRequestResource($parentRequest->load('messages')));
    }

    public function reply(MessageRequest $request, ParentRequest $parentRequest, MessageService $messages): JsonResponse
    {
        $this->authorize('manage', $parentRequest);
        $message = $messages->replyToParent($parentRequest->load('centre'), $request->user(), $request->validated('body'));

        return $this->created(new MessageResource($message), 'Reply sent. '.$parentRequest->parent_name.' gets an email copy.');
    }

    public function updateStatus(ParentRequestStatusRequest $request, ParentRequest $parentRequest): JsonResponse
    {
        $this->authorize('manage', $parentRequest);
        $parentRequest->forceFill(array_filter([
            'status' => $request->validated('status'),
            'requested_at' => $request->validated('requested_at'),
            'responded_at' => $parentRequest->responded_at ?? now(),
        ], fn ($value) => $value !== null))->save();

        return $this->ok(new ParentRequestResource($parentRequest->load('messages')), 'Updated.');
    }
}
