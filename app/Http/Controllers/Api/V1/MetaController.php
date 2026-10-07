<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Shared vocabularies (employment types, categories, ...) so the frontend and
 * backend validation always agree.
 */
class MetaController extends Controller
{
    private const GROUPS = [
        'employment_types', 'job_categories', 'education_requirements', 'experience_levels', 'certifications',
        'practicum_levels', 'salary_periods', 'application_statuses', 'teaching_approaches', 'licence_types',
        'program_schedules', 'program_availability', 'gallery_categories', 'contact_methods',
        'profile_visibility', 'job_alert_frequencies',
    ];

    public function options(): JsonResponse
    {
        $options = collect(self::GROUPS)->mapWithKeys(fn ($group) => [
            $group => collect(config("platform.{$group}"))->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ])->all();

        $options['age_groups'] = collect(config('platform.age_groups'))
            ->map(fn ($group, $key) => ['key' => $key] + $group)->values();
        $options['oauth_providers'] = collect(['google', 'linkedin'])
            ->filter(fn ($p) => config("services.{$p}.client_id") && config("services.{$p}.client_secret"))
            ->values();

        return response()->json(['success' => true, 'message' => 'Request successful.', 'data' => $options])
            ->setPublic()->setMaxAge(300);
    }
}
