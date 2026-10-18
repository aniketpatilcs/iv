<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Normalizes the display-formatted strings the portal forms submit
 * (e.g. "$1,250.00", "04/17/2026") into storable values.
 */
final class FormValue
{
    public static function text(mixed $value, int $max = 255): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    public static function money(mixed $value): ?string
    {
        $value = preg_replace('/[^0-9.]/', '', (string) self::text($value));

        return is_numeric($value) && (float) $value < 1e10 ? number_format((float) $value, 2, '.', '') : null;
    }

    public static function date(mixed $value): ?string
    {
        $value = self::text($value, 10);

        if ($value === null || ! preg_match('#^\d{2}/\d{2}/\d{4}$#', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!m/d/Y', $value);

        return $date && $date->format('m/d/Y') === $value ? $date->toDateString() : null;
    }

    public static function join(mixed ...$parts): ?string
    {
        return self::text(implode(' ', array_filter(array_map(fn ($part) => self::text($part), $parts))));
    }
}
