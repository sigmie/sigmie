<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Term;

use Sigmie\Base\Search\Queries\Query;

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
                'field' => $this->field
            ]
        ];
    }
}
