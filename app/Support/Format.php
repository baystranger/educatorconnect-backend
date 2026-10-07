<?php

namespace App\Support;

/**
 * Display helpers shared by API resources so every screen formats data the same way.
 */
class Format
{
    public static function option(string $group, ?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }
        $label = config("platform.{$group}.{$key}");
        if (is_array($label)) {
            $label = $label['label'] ?? $key;
        }

        return ['key' => $key, 'label' => $label ?? $key];
    }

    public static function options(string $group, ?array $keys): array
    {
        return collect($keys ?? [])->map(fn ($key) => self::option($group, $key))->filter()->values()->all();
    }

    public static function pay(?string $min, ?string $max, ?string $period): ?string
    {
        if ($min === null && $max === null) {
            return null;
        }
        $suffix = $period === 'year' ? '/yr' : '/hr';
        $money = function ($value) use ($period) {
            $value = (float) $value;
            if ($period === 'year' && $value >= 1000) {
                return '$'.rtrim(rtrim(number_format($value / 1000, 1), '0'), '.').'k';
            }

            return '$'.(fmod($value, 1.0) === 0.0 ? number_format($value) : number_format($value, 2));
        };

        if ($min !== null && $max !== null && (float) $min !== (float) $max) {
            return $money($min).'–'.$money($max).$suffix;
        }

        return $money($min ?? $max).$suffix;
    }

    public static function ageMonths(?int $months): ?string
    {
        if ($months === null) {
            return null;
        }
        if ($months < 24) {
            return $months.' mo';
        }
        $years = $months / 12;

        return (fmod($years, 1.0) === 0.0 ? (int) $years : rtrim(rtrim(number_format($years, 1), '0'), '.')).' yrs';
    }

    public static function ageRange(?int $min, ?int $max): ?string
    {
        if ($min === null && $max === null) {
            return null;
        }
        if ($min === null) {
            return 'Up to '.self::ageMonths($max);
        }
        if ($max === null) {
            return self::ageMonths($min).'+';
        }
        $from = self::ageMonths($min);
        $to = self::ageMonths($max);
        // "6 mo–5 yrs" / "3–5 yrs"
        if (str_ends_with($from, ' yrs') && str_ends_with($to, ' yrs')) {
            $from = substr($from, 0, -4);
        }

        return $from.'–'.$to;
    }

    public static function initials(?string $name): string
    {
        $words = array_values(array_filter(preg_split('/[\s\-]+/', trim((string) $name)) ?: [], fn ($w) => preg_match('/^\p{L}/u', $w)));
        $letters = array_map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)), array_slice($words, 0, 2));

        return implode('', $letters) ?: '?';
    }

    public static function distance(mixed $km): ?float
    {
        return $km === null ? null : round((float) $km, 1);
    }

    /** Splits a multi-line text field into trimmed, non-empty lines. */
    public static function lines(?string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $text))
            ->map(fn ($line) => trim(ltrim(trim($line), '-•*')))
            ->filter()
            ->values()
            ->all();
    }
}
