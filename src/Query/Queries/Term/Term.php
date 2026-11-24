<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Term;

use Sigmie\Query\Queries\Query;

use function Sigmie\Functions\keyed;

class Term extends Query
{
    public function __construct(
        protected string $field,
        protected int|string|bool|float $value
    ) {}

    public function toRaw(): array
    {
        return [
            'term' => keyed($this->field, [
                'value' => $this->value,
                'boost' => $this->boost,
            ]),
        ];
    }
}
