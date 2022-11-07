<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Term;

use Sigmie\Query\Queries\Query;

class IDs extends Query
{
    public function __construct(
        protected array $ids
    ) {
    }

    public function toRaw(): array
    {
        return [
            'ids' => [
                'values' => $this->ids,
                'boost'=> $this->boost
            ],
        ];
    }
}
