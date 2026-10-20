<?php

namespace App\Support;

use Illuminate\Support\Str;

class PersonName
{
    /**
     * Splits a full name into [first, last] on the first space: "Mary Ann Lee" → ["Mary", "Ann Lee"].
     *
     * @return array{0: string, 1: string}
     */
    public static function split(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [Str::limit($parts[0] ?? '', 100, ''), Str::limit($parts[1] ?? '', 100, '')];
    }

    /** "Mary" + "Lee" → "Mary Lee", skipping empty parts. */
    public static function join(?string $first, ?string $last): string
    {
        return trim(trim((string) $first).' '.trim((string) $last));
    }
}
