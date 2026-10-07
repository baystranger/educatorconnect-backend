<?php

namespace App\Http\Resources;

use App\Support\ApplicationStages;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An application as the applying educator sees it on "My jobs".
 *
 * @mixin \App\Models\JobApplication
 */
class EducatorApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => ['key' => $this->status, 'label' => ApplicationStages::label($this->status)],
            'step' => ApplicationStages::step($this->status),
            'step_count' => count(ApplicationStages::PIPELINE),
            'is_closed' => in_array($this->status, ApplicationStages::CLOSED, true),
            'can_withdraw' => in_array($this->status, ApplicationStages::ACTIVE, true),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'updated_at' => ($this->status_changed_at ?? $this->applied_at)?->toIso8601String(),
            'interview' => $this->interview_at ? [
                'at' => $this->interview_at->toIso8601String(),
                'location' => $this->interview_location,
                'details' => $this->interview_details,
            ] : null,
            'job' => $this->whenLoaded('job', fn () => (new JobResource($this->job))->resolve($request)),
            'timeline' => $this->whenLoaded('events', fn () => $this->events->map(fn ($event) => [
                'status' => $event->status,
                'label' => $this->timelineLabel($event->status),
                'note' => $event->note,
                'at' => $event->created_at?->toIso8601String(),
            ])->values()),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
        ];
    }

    private function timelineLabel(string $status): string
    {
        return match ($status) {
            'new' => 'Application submitted',
            'reviewed' => 'Reviewed by centre',
            'interview' => 'Interview scheduled',
            'withdrawn' => 'You withdrew this application',
            default => ApplicationStages::label($status),
        };
    }
}
