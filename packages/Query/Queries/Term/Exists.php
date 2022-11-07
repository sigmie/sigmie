<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Term;

use Sigmie\Query\Queries\Query;

class Exists extends Query
{
    public function __construct(
        protected string $field,
    ) {
    }

    public function toRaw(): array
    {
        return [
            'exists' => [
                'field' => $this->field,
                'boost'=> $this->boost
            ],
        ];
    }
}
