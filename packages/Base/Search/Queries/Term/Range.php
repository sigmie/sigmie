<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Term;

use Sigmie\Base\Search\Queries\Query;

class Range extends Query
{
    public function __construct(
        protected string $field,
        protected null|float|int|string $min = null,
        protected null|float|int|string $max = null,
    ) {
    }
    private function esOperator(string $operator): string
    {
        return match ($operator) {
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
            default => throw new \Exception('Range operator not supported.')
        };
    }

    public function toRaw(): array
    {
        $res = [
            'range' => [
                $this->field => []
            ]
        ];

        if (!is_null($this->min)) {
            $operator = $this->esOperator('>=');
            $res['range'][$this->field][$operator] = $this->min;
        }

        if (!is_null($this->max)) {
            $operator = $this->esOperator('<=');
            $res['range'][$this->field][$operator] = $this->max;
        }

        return $res;
    }
}
