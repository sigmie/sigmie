<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Text;

use Sigmie\Query\Queries\Query;

class MatchPhrase extends Query
{
    public function __construct(
        protected string $field,
        protected string $query,
        protected string $analyzer = 'default',
    ) {}

    public function toRaw(): array
    {
        $raw = [
            'match_phrase' => [
                $this->field => [
                    'query' => $this->query,
                    'boost' => $this->boost,
                    'analyzer' => $this->analyzer,
                ],
            ],
        ];

        return $raw;
    }
}
