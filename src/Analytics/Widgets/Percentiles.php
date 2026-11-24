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

        $byPercent = [];

        foreach ($values as $percent => $value) {
            if (! is_numeric($percent)) {
                continue;
            }

            $byPercent[json_encode((float) $percent, JSON_THROW_ON_ERROR)] = $value;
        }

        $percentiles = [];
        foreach ($this->percents as $percent) {
            $key = json_encode((float) $percent, JSON_THROW_ON_ERROR);
            if (array_key_exists($key, $byPercent)) {
                $percentiles[$key] = $byPercent[$key];
            }
        }

        $percentiles += $byPercent;

        // Keys 0, 1, 2… are a PHP list. json_encode would drop those percentile labels.
        if ($percentiles !== [] && array_is_list($percentiles)) {
            $percentiles = (object) $percentiles;
        }

        return [
            'type' => 'percentiles',
            'field' => $this->field,
            'percentiles' => $percentiles,
        ];
    }
}
