<?php

declare(strict_types=1);

namespace Sigmie\Analytics\Widgets;

use DateTimeInterface;
use Sigmie\Query\Aggs;
use Sigmie\Shared\Collection;

/**
 * The actual matching documents for the window/slice — the rows behind a number, not a metric
 * ("the 20 most recent orders", "the latest errors in this slice"). Backed by a `top_hits`
 * aggregation, so it composes with the same per-widget filter() and window as every other widget.
 */
class Table extends Widget
{
    /**
     * @param  list<string>  $fields  Source fields to return per row; empty returns the full document.
     * @param  array<int, array<string, mixed>>|null  $sort  Elasticsearch sort clauses.
     */
    public function __construct(
        string $name,
        string $dateField,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $dateFormat,
        protected array $fields,
        protected int $limit,
        protected ?array $sort,
    ) {
        parent::__construct($name, $dateField, $from, $to, $dateFormat);
    }

    public function toRaw(): array
    {
        return $this->scoped($this->name, $this->from, $this->to, function (Aggs $aggs): void {
            $aggs->topHits('hits', size: $this->limit, sourceIncludes: $this->fields === [] ? null : $this->fields, sort: $this->sort);
        })->toRaw();
    }

    public function extract(array $aggregations): array
    {
        $hits = new Collection($aggregations[$this->name]['hits']['hits']['hits'] ?? []);

        return [
            'type' => 'table',
            'rows' => $hits->map(fn (array $hit): array => [
                'id' => $hit['_id'] ?? null,
                'document' => $hit['_source'] ?? [],
            ])->toArray(),
        ];
    }
}
