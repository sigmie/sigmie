<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Queries\Query;

class Nested extends Query
{
    public function __construct(
        protected string $path,
        protected Query $query,
        protected string $scoreMode = 'avg',
    ) {}

    public function toRaw(): array
    {
        $raw = [
            'nested' => [
                'path' => $this->path,
                'query' => $this->query->toRaw(),
                'boost' => $this->boost,
            ],
        ];

        return $raw;
    }
}
