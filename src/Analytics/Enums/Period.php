<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Enums;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A named relative time window ("this month", "last 7 days", …). Resolved to concrete
 * [from, to) instants at build time — in whatever timezone the {@see \Sigmie\Analytics\Analytics}
 * builder is set to — so the Elasticsearch query stays cacheable.
 *
 * Weeks start on Monday (ISO-8601).
 */
enum Period: string
{
    case Today = 'today';

    case Yesterday = 'yesterday';

    case ThisWeek = 'this_week';

    case LastWeek = 'last_week';

    case ThisMonth = 'this_month';

    case LastMonth = 'last_month';

    case ThisQuarter = 'this_quarter';

    case LastQuarter = 'last_quarter';

    case ThisYear = 'this_year';

    case LastYear = 'last_year';

    case Last7Days = 'last_7_days';

    case Last30Days = 'last_30_days';

    case Last90Days = 'last_90_days';

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable} [from (inclusive), to (exclusive)]
     */
    public function resolve(DateTimeImmutable $now): array
    {
        // Do calendar arithmetic before resolving the local boundaries. A zone can skip an entire date.
        $utc = new DateTimeZone('UTC');
        $day = self::civilDate($now, $utc);
        $nextDay = $day->modify('+1 day');

        [$from, $to] = match ($this) {
            self::Today => [$day, $nextDay],
            self::Yesterday => [$day->modify('-1 day'), $day],
            self::ThisWeek => [$this->weekStart($day), $this->weekStart($day)->modify('+1 week')],
            self::LastWeek => [$this->weekStart($day)->modify('-1 week'), $this->weekStart($day)],
            self::ThisMonth => [$this->monthStart($day), $day->modify('first day of next month')],
            self::LastMonth => [$day->modify('first day of last month'), $this->monthStart($day)],
            self::ThisQuarter => [$this->quarterStart($day), $this->quarterStart($day)->modify('+3 months')],
            self::LastQuarter => [$this->quarterStart($day)->modify('-3 months'), $this->quarterStart($day)],
            self::ThisYear => [$this->yearStart($day), $this->yearStart($day)->modify('+1 year')],
            self::LastYear => [$this->yearStart($day)->modify('-1 year'), $this->yearStart($day)],
            self::Last7Days => [$day->modify('-6 days'), $nextDay],
            self::Last30Days => [$day->modify('-29 days'), $nextDay],
            self::Last90Days => [$day->modify('-89 days'), $nextDay],
        };

        $zone = $now->getTimezone();

        return [
            self::startOfDay(self::civilDate($from, $zone)),
            self::startOfDay(self::civilDate($to, $zone)),
        ];
    }

    private static function civilDate(DateTimeImmutable $date, DateTimeZone $zone): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('%+05d-%s', (int) $date->format('Y'), $date->format('m-d')), $zone);
    }

    /**
     * The `modify()` step from this period's start to the previous instance's start, for a
     * calendar-aware KPI delta ("this month vs last month"). Null = compare against an equal
     * duration (the right behaviour for day/rolling windows).
     */
    public function previousModifier(): ?string
    {
        return match ($this) {
            self::ThisWeek, self::LastWeek => '-1 week',
            self::ThisMonth, self::LastMonth => '-1 month',
            self::ThisQuarter, self::LastQuarter => '-3 months',
            self::ThisYear, self::LastYear => '-1 year',
            default => null,
        };
    }

    public static function startOfDay(DateTimeImmutable $date): DateTimeImmutable
    {
        $midnight = $date->setTime(0, 0, 0);
        $timestamp = $midnight->getTimestamp();

        $zone = $midnight->getTimezone();
        $transitions = $zone->getTransitions($timestamp - 86400, $timestamp + 1) ?: [];
        $wallMidnight = $timestamp + $midnight->getOffset()
            - ((int) $midnight->format('H') * 3600 + (int) $midnight->format('i') * 60 + (int) $midnight->format('s'));

        $previousOffset = $transitions[0]['offset'] ?? $midnight->getOffset();
        foreach (array_slice($transitions, 1) as $transition) {
            $before = $transition['ts'] + $previousOffset;
            $after = $transition['ts'] + $transition['offset'];

            // A midnight skipped by a forward change starts at the transition.
            if ($before <= $wallMidnight && $wallMidnight < $after) {
                return (new DateTimeImmutable('@'.$transition['ts']))->setTimezone($zone);
            }

            // A midnight repeated by a backward change starts at its first occurrence.
            if ($after <= $wallMidnight && $wallMidnight < $before) {
                return (new DateTimeImmutable('@'.($wallMidnight - $previousOffset)))->setTimezone($zone);
            }

            $previousOffset = $transition['offset'];
        }

        return $midnight;
    }

    private function weekStart(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify('monday this week');
    }

    private function monthStart(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify('first day of this month');
    }

    private function quarterStart(DateTimeImmutable $now): DateTimeImmutable
    {
        $startMonth = intdiv((int) $now->format('n') - 1, 3) * 3 + 1;

        return $now->setDate((int) $now->format('Y'), $startMonth, 1);
    }

    private function yearStart(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->setDate((int) $now->format('Y'), 1, 1);
    }
}
