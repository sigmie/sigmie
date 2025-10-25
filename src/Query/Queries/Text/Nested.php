<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\Queries\Query;

class Nested extends Query
{
    public function __construct(
        protected string $path,
        protected QueryClause $query,
        protected string $scoreMode = 'avg',
    ) {}

    public function toRaw(): array
    {
        return [
            'nested' => [
                'path' => $this->path,
                'score_mode' => $this->scoreMode,
                'query' => $this->query->toRaw(),
                'boost' => $this->boost,
            ],
        ];
    }
}
