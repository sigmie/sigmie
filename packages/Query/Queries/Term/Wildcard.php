<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Term;

use Sigmie\Query\Queries\Query;

class Wildcard extends Query
{
    public function __construct(
        protected string $field,
        protected string $value
    ) {
    }

    public function toRaw(): array
    {
        return [
            'wildcard' => [
                $this->field => [
                    'value' => $this->value,
                    'boost' => $this->boost
                ],
            ],
        ];
    }
}
