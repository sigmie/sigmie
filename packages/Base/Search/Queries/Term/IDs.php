<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Term;

use Sigmie\Base\Search\Queries\Query;

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
