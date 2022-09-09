<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Term;

use Sigmie\Base\Search\Queries\Query;

class Term extends Query
{
    public function __construct(
        protected string $field,
        protected int|string|bool $value
    ) {
    }

    public function toRaw(): array
    {
        return [
            'term' => [
                $this->field => [
                    'value' => $this->value,
                    'boost' => $this->boost
                ],
            ],
        ];
    }
}
