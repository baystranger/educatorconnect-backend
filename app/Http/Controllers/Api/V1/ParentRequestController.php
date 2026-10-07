<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParentRequestRequest;
use App\Models\ChildcareCentre;
use App\Notifications\ParentRequestConfirmationNotification;
use App\Notifications\ParentRequestReceivedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;

/**
 * Families contact a centre from its public profile without an account.
 */
class ParentRequestController extends Controller
{
    public function store(ParentRequestRequest $request, string $slug): JsonResponse
    {
        $centre = ChildcareCentre::query()->where('slug', $slug)->publiclyVisible()->firstOrFail();

        $item = $centre->parentRequests()->make(Arr::except($request->validated(), ['consent', 'website']));
        $item->forceFill(['status' => 'new', 'consent_at' => now()])->save();

        $centre->owner->notify(new ParentRequestReceivedNotification($item));
        Notification::route('mail', [$item->email => $item->parent_name])
            ->notify(new ParentRequestConfirmationNotification($item));

        $message = match ($item->type) {
            'tour' => 'Tour request sent. '.$centre->name.' will confirm by email.',
            'waitlist' => 'You are on the list. '.$centre->name.' will be in touch by email.',
            default => 'Question sent. '.$centre->name.' usually replies within a day.',
        };

        return $this->created(['id' => $item->id, 'type' => $item->type], $message);
    }
}
