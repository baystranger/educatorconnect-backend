<?php

namespace App\Support;

/**
 * The hiring pipeline: New → Reviewed → Shortlisted → Interview → Offered → Hired, with
 * Not selected / Withdrawn as closed outcomes.
 */
class ApplicationStages
{
    public const PIPELINE = ['new', 'reviewed', 'shortlisted', 'interview', 'offered', 'hired'];
    public const CLOSED = ['not_selected', 'withdrawn'];
    public const ACTIVE = ['new', 'reviewed', 'shortlisted', 'interview', 'offered'];

    /** Statuses a centre may set. */
    public const CENTRE_SETTABLE = ['new', 'reviewed', 'shortlisted', 'interview', 'offered', 'hired', 'not_selected'];

    public static function step(string $status): int
    {
        $index = array_search($status, self::PIPELINE, true);

        return $index === false ? 0 : $index + 1;
    }

    public static function next(string $status): ?string
    {
        $index = array_search($status, self::PIPELINE, true);

        return $index === false || $index === count(self::PIPELINE) - 1 ? null : self::PIPELINE[$index + 1];
    }

    public static function isClosed(string $status): bool
    {
        return in_array($status, self::CLOSED, true) || $status === 'hired';
    }

    public static function label(string $status): string
    {
        return config('platform.application_statuses.'.$status, ucfirst(str_replace('_', ' ', $status)));
    }

    /** Educator "My jobs" filter tabs. */
    public static function filterStatuses(string $filter): ?array
    {
        return match ($filter) {
            'in_progress' => ['new', 'reviewed', 'shortlisted'],
            'interviews' => ['interview'],
            'offers' => ['offered', 'hired'],
            'closed' => self::CLOSED,
            default => null,
        };
    }
}
