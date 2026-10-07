<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A posting as its owning centre sees it in "Job postings" and the dashboard table.
 *
 * @mixin \App\Models\Job
 */
class CentreJobResource extends JobResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'views' => (int) $this->views,
            'applicants_count' => (int) ($this->applications_count ?? 0),
            'new_applicants_count' => (int) ($this->new_applications_count ?? 0),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ]);
    }
}
