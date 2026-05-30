<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Query\Aggs;

/**
 * Percentiles of a numeric field for the period — the latency-style summary
 * (p50 / p75 / p95 / p99 of response time, order value…).
 */
class Percentiles extends Widget
{
    /**
     * @param  list<int|float>  $percents
     */
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected string $field,
        protected array $percents,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->percentiles('metric', $this->field, $this->percents);
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $values = $aggregations[$this->name]['metric']['values'] ?? [];

        $percentiles = [];

        foreach ($values as $percent => $value) {
            $percentiles[(string) (float) $percent] = $value;
        }

        return [
            'type' => 'percentiles',
            'field' => $this->field,
            'percentiles' => $percentiles,
        ];
    }
}
