<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Term;

use Sigmie\Base\Search\Queries\QueryClause;

class Range extends QueryClause
{
    public function __construct(
        protected string $field,
        protected string $operator,
        protected string $value,
    ) {
    }

    public function toRaw(): array
    {
        $operator = match ($this->operator) {
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
            default => throw new \Exception('Range operator not supported.')
        };

        return [
            'range' => [
                $this->field => [
                    $operator => $this->value
                ]
            ]
        ];
    }
}
