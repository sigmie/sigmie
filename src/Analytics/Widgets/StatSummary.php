<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Query\Aggs;

/**
 * The five-number summary of a numeric field in one tile — count, min, max, avg and sum
 * ("order-size summary", "response-time summary"). Backed by the `stats` aggregation.
 */
class StatSummary extends Widget
{
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected string $field,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->stats('stats', $this->field);
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $stats = $aggregations[$this->name]['stats'] ?? [];

        return [
            'type' => 'stats',
            'field' => $this->field,
            'count' => $stats['count'] ?? 0,
            'min' => $stats['min'] ?? null,
            'max' => $stats['max'] ?? null,
            'avg' => $stats['avg'] ?? null,
            'sum' => $stats['sum'] ?? null,
        ];
    }
}
