<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Term;

use Sigmie\Query\Queries\Query;

class Prefix extends Query
{
    public function __construct(
        protected string $field,
        protected int|string|bool|float $value,
        protected bool $caseInsensitive = true
    ) {
    }

    public function toRaw(): array
    {
        return [
            'prefix' => [
                $this->field => [
                    'value' => $this->value,
                    'case_insensitive' => $this->caseInsensitive,
                    'boost' => $this->boost,
                ],
            ],
        ];
    }
}
