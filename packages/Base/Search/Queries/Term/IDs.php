<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Term;

use Sigmie\Base\Search\Queries\QueryClause;

class IDs extends QueryClause
{
    public function __construct(
        protected array $ids
    ) {
    }

    public function toRaw(): array
    {
        return [
            'ids' => [
                'value' => $this->ids
            ]
        ];
    }
}
