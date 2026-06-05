<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class Filter extends Bucket
{
    public function __construct(
        protected string $name,
        protected $query,
    ) {
        parent::__construct($name);
    }

    /**
     * Just the filter query — sub-aggregations are added by {@see Bucket::toRaw()} only when
     * aggregate() set them, so a bare filter (e.g. a funnel step counting doc_count) doesn't emit
     * an empty `aggs: []`, which Elasticsearch rejects.
     */
    protected function value(): array
    {
        return [
            'filter' => [
                ...$this->query->toRaw(),
            ],
        ];
    }
}
