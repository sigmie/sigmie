<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Enums;

use DateTimeImmutable;

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
        $startOfDay = $now->setTime(0, 0, 0);

        return match ($this) {
            self::Today => [$startOfDay, $startOfDay->modify('+1 day')],
            self::Yesterday => [$startOfDay->modify('-1 day'), $startOfDay],
            self::ThisWeek => [$w = $this->weekStart($now), $w->modify('+1 week')],
            self::LastWeek => [($w = $this->weekStart($now))->modify('-1 week'), $w],
            self::ThisMonth => [$m = $this->monthStart($now), $m->modify('+1 month')],
            self::LastMonth => [($m = $this->monthStart($now))->modify('-1 month'), $m],
            self::ThisQuarter => [$q = $this->quarterStart($now), $q->modify('+3 months')],
            self::LastQuarter => [($q = $this->quarterStart($now))->modify('-3 months'), $q],
            self::ThisYear => [$y = $this->yearStart($now), $y->modify('+1 year')],
            self::LastYear => [($y = $this->yearStart($now))->modify('-1 year'), $y],
            self::Last7Days => [($t = $startOfDay->modify('+1 day'))->modify('-7 days'), $t],
            self::Last30Days => [($t = $startOfDay->modify('+1 day'))->modify('-30 days'), $t],
            self::Last90Days => [($t = $startOfDay->modify('+1 day'))->modify('-90 days'), $t],
        };
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

    private function weekStart(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify('monday this week')->setTime(0, 0, 0);
    }

    private function monthStart(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify('first day of this month')->setTime(0, 0, 0);
    }

    private function quarterStart(DateTimeImmutable $now): DateTimeImmutable
    {
        $startMonth = intdiv((int) $now->format('n') - 1, 3) * 3 + 1;

        return $now->setDate((int) $now->format('Y'), $startMonth, 1)->setTime(0, 0, 0);
    }

    private function yearStart(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0, 0);
    }
}
