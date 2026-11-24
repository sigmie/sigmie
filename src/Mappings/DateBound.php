<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\DateTime;

final class DateBound
{
    public static function accepts(mixed $bound, string $formats, bool $query = false): bool
    {
        if (! is_string($bound) && ! is_int($bound) && ! is_float($bound)) {
            return false;
        }
        $text = is_float($bound) ? json_encode($bound) : (string) $bound;
        if (! is_string($text) || $text === '') {
            return false;
        }
        $relative = $query && str_starts_with($text, 'now');
        [$base, $math] = $relative
            ? ['now', substr($text, 3)]
            : array_pad(explode('||', $text, 2), 2, '');
        if (! preg_match('~^(?:(?:[+-][0-9]*|/(?:0*1)?)[yMwdhHms])*\z~', $math)) {
            return false;
        }
        if ($relative) {
            return true;
        }

        foreach (explode('||', $formats) as $format) {
            if ($format === 'strict_date_optional_time') {
                if (self::validIsoDateBound($base)) {
                    return true;
                }
            } elseif ($format === 'strict_date_optional_time_nanos' && $query && (! str_contains($base, 'T') || preg_match('/T\d{2}:\d{2}:\d{2}/', $base))
                && self::validIsoDateBound($base)) {
                return true;
            } elseif ((new Date('date', [$format]))->validate('date', $base)[0]
                || (new DateTime('date', [$format]))->validate('date', $base)[0]) {
                return true;
            }
        }

        return false;
    }

    private static function validIsoDateBound(string $base): bool
    {
        if (preg_match('/^(?!-0000)-?\d{4}\z/', $base)) {
            return true;
        }
        if (! preg_match('/^-?(\d{4})-(\d{2})(?:-(\d{2})(?:T(.*))?)?\z/', $base, $date)) {
            return false;
        }
        if (($date[4] ?? '') !== '') {
            if (! preg_match('/^(\d{2})(?::(\d{2})(?::(\d{2})([.,]\d{1,9})?)?)?(Z|[+-]\d{2}(?::?\d{2}(?::?\d{2})?)?)?\z/', $date[4], $time)) {
                return false;
            }
            $zone = $time[5] ?? '';
            if (strlen($zone) === 3) {
                $zone .= ':00';
            }
            $timestamp = substr($base, 0, strpos($base, 'T') + 1).$time[1].':'.(($time[2] ?? '') ?: '00')
                .':'.(($time[3] ?? '') ?: '00').str_replace(',', '.', $time[4] ?? '').$zone;

            return (new DateTime('date'))->validate('date', $timestamp)[0];
        }

        // Shift into an equivalent Gregorian cycle so checkdate also handles year zero.
        return checkdate((int) $date[2], (int) ($date[3] ?? 1), 2000 + (int) $date[1] % 400);
    }
}
