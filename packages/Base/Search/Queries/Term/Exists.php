<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Term;

use Sigmie\Base\Search\Queries\QueryClause;

class Exists extends QueryClause
{
    public function __construct(
        protected string $field,
    ) {
    }

    public function toRaw(): array
    {
        return [
            'exists' => [
                'filed' => $this->field
            ]
        ];
    }
}
