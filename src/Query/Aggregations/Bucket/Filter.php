<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Aggs;

class Filter extends Bucket
{
    public function __construct(
        protected string $name,
        protected $query,
    ) {
        parent::__construct($name);
        $this->aggs = new Aggs;
    }

    protected function value(): array
    {
        return [
            'filter' => [
                ...$this->query->toRaw(),
            ],
            'aggs' => $this->aggs->toRaw(),
        ];
    }
}
