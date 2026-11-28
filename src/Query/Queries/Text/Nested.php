<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Contracts\QueryClause;
use Sigmie\Query\Queries\Query;
use Sigmie\Search\InnerHits;

class Nested extends Query
{
    public function __construct(
        protected string $path,
        protected QueryClause $query,
        protected string $scoreMode = 'avg',
        protected ?InnerHits $innerHits = null,
    ) {}

    public function toRaw(): array
    {
        $raw = [
            'nested' => [
                'path' => $this->path,
                'score_mode' => $this->scoreMode,
                'query' => $this->query->toRaw(),
                'boost' => $this->boost,
                // A search over several indices can reach one without this path.
                'ignore_unmapped' => true,
            ],
        ];

        if ($innerHits = $this->innerHits?->clause($this->path)) {
            $raw['nested']['inner_hits'] = $innerHits;
        }

        return $raw;
    }
}
