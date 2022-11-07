<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Term;

use Sigmie\Query\Queries\Query;

class Regex extends Query
{
    public function __construct(
        protected string $field,
        protected string $value,
    ) {
    }

    public function toRaw(): array
    {
        return [
            'regexp' => [
                $this->field => [
                    'value' => $this->value,
                    'boost' => $this->boost
                ],
            ],
        ];
    }
}
