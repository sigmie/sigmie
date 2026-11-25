<?php

declare(strict_types=1);

namespace Sigmie\Analytics;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/** Keeps the fractional digits that DateTimeImmutable drops. */
final class WindowBound extends DateTimeImmutable
{
    private int $nanosecondsWithinMicrosecond = 0;

    public static function fromIso(string $value): DateTimeImmutable
    {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        preg_match('/\.\d{6}(\d{1,3})(?=Z|[+-])/', $value, $fraction);
        $remainder = (int) str_pad($fraction[1] ?? '', 3, '0');

        return self::withRemainder($date, $remainder);
    }

    public static function copy(DateTimeInterface $time): DateTimeImmutable
    {
        return $time instanceof self ? $time : DateTimeImmutable::createFromInterface($time);
    }

    public static function remainder(DateTimeInterface $time): int
    {
        return $time instanceof self ? $time->nanosecondsWithinMicrosecond : 0;
    }

    public static function withRemainder(DateTimeImmutable $time, int $remainder): DateTimeImmutable
    {
        if ($remainder === 0) {
            return $time instanceof self ? DateTimeImmutable::createFromInterface($time) : $time;
        }

        $date = self::createFromInterface($time);
        $date->nanosecondsWithinMicrosecond = $remainder;

        return $date;
    }
}
