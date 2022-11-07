<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Term;

use Sigmie\Query\Queries\Query;

class Terms extends Query
{
    public function __construct(
        protected string $field,
        protected array $values
    ) {
    }

    public function toRaw(): array
    {
        return [
            'terms' => [
                $this->field => $this->values,
                'boost' => $this->boost
            ],
        ];
    }
}
