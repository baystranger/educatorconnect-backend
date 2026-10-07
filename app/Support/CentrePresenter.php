<?php

namespace App\Support;

use App\Models\CentreProgram;
use App\Models\ChildcareCentre;
use Illuminate\Support\Collection;

/**
 * Derived, display-ready facts about a centre (age range, availability badge, hours summary).
 * Expects `programs` to be loaded when program-derived values are needed.
 */
class CentrePresenter
{
    public static function ageRange(ChildcareCentre $centre): ?string
    {
        $programs = self::programs($centre);
        if ($programs->isEmpty()) {
            return null;
        }

        return Format::ageRange($programs->min('min_age_months'), $programs->max('max_age_months'));
    }

    /**
     * @return array{label: string, tone: string}|null
     */
    public static function availability(ChildcareCentre $centre): ?array
    {
        $programs = self::programs($centre);
        if ($programs->isEmpty()) {
            return null;
        }

        $open = $programs->where('availability', CentreProgram::AVAILABILITY_SPACES);
        if ($open->count() === 1) {
            return ['label' => $open->first()->name.' spaces', 'tone' => 'green'];
        }
        if ($open->count() > 1) {
            return ['label' => 'Spaces available', 'tone' => 'green'];
        }
        if ($programs->contains('availability', CentreProgram::AVAILABILITY_WAITLIST)) {
            return ['label' => 'Waitlist open', 'tone' => 'yellow'];
        }

        return ['label' => 'Full', 'tone' => 'gray'];
    }

    /** "Toddler spaces available now" style summary for the parent request panel. */
    public static function availabilityDetail(ChildcareCentre $centre): ?array
    {
        $programs = self::programs($centre);
        if ($programs->isEmpty()) {
            return null;
        }
        $open = $programs->where('availability', CentreProgram::AVAILABILITY_SPACES)->pluck('name');
        $others = $programs->where('availability', '!=', CentreProgram::AVAILABILITY_SPACES)
            ->map(fn ($p) => $p->name.': '.($p->availability === CentreProgram::AVAILABILITY_WAITLIST ? 'waitlist' : 'full'))
            ->implode(' · ');

        return [
            'headline' => $open->isNotEmpty() ? $open->implode(' · ').' spaces available now' : 'No open spaces right now',
            'detail' => $others ?: null,
            'has_spaces' => $open->isNotEmpty(),
        ];
    }

    public static function hoursSummary(ChildcareCentre $centre, bool $compact = true): ?string
    {
        $first = collect($centre->hours_of_operation ?? [])->first(fn ($row) => ! empty($row['value']) && stripos($row['value'], 'closed') === false);
        if (! $first) {
            return null;
        }
        $label = str_replace(' – ', '–', $first['label'] ?? '');
        $value = $first['value'];
        if ($compact) {
            $value = str_replace([' AM', ' PM', ' am', ' pm'], '', $value);
            $value = str_replace(' – ', '–', $value);
        }

        return trim($label.' · '.$value, ' ·');
    }

    public static function tags(ChildcareCentre $centre): array
    {
        return array_values(array_filter(array_merge(
            array_column(Format::options('teaching_approaches', $centre->teaching_approaches), 'label'),
            $centre->highlights ?? [],
        )));
    }

    public static function tuitionRange(ChildcareCentre $centre): ?string
    {
        $amounts = self::programs($centre)->pluck('tuition_amount')->filter()->map(fn ($a) => (float) $a);
        if ($amounts->isEmpty()) {
            return null;
        }
        $min = $amounts->min();
        $max = $amounts->max();
        $fmt = fn ($v) => '$'.number_format($v);

        return ($min === $max ? $fmt($min) : $fmt($min).'–'.$fmt($max)).' / mo';
    }

    public static function coverPath(ChildcareCentre $centre): ?string
    {
        if ($centre->cover_image_path) {
            return $centre->cover_image_path;
        }
        if ($centre->relationLoaded('gallery')) {
            $cover = $centre->gallery->firstWhere('is_cover', true) ?? $centre->gallery->firstWhere('type', 'image');

            return $cover?->file_path;
        }

        return null;
    }

    private static function programs(ChildcareCentre $centre): Collection
    {
        return $centre->relationLoaded('programs')
            ? $centre->programs->where('is_active', true)
            : collect();
    }
}
