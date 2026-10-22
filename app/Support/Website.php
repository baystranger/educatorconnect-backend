<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Website addresses for centres, educators and professionals. People type "littlesprouts.ca";
 * we store "https://littlesprouts.ca" so every link the frontend renders is absolute.
 */
class Website
{
    private const PATTERN = '/^(https?:\/\/)?[\w.-]+\.[a-z]{2,}(:\d+)?(\/\S*)?$/i';

    /** Validation rules for an optional website field. */
    public static function rules(): array
    {
        return ['nullable', 'string', 'max:255', 'regex:'.self::PATTERN];
    }

    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return preg_match('/^https?:\/\//i', $value) ? $value : 'https://'.$value;
    }

    /** Model attribute that normalises on write. */
    public static function attribute(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => self::normalize($value));
    }
}
