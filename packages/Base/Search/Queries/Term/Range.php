<?php

declare(strict_types=1);

namespace Sigmie\Base\Search\Queries\Term;

use Exception;
use Sigmie\Base\Search\Queries\Query;

class Range extends Query
{
    /**
     * values example: [['>=', '2'],['<=','200']]
     */
    public function __construct(
        protected string $field,
        protected array $values = [],
    ) {
    }

    public function toRaw(): array
    {
        $res = [
            'range' => [
                $this->field => []
            ]
        ];

        foreach ($this->values as $operator => $value) {
            $operator = match ($operator) {
                '>' => 'gt',
                '>=' => 'gte',
                '<' => 'lt',
                '<=' => 'lte',
                default => throw new Exception('Range operator is required')
            };
            $res['range'][$this->field][$operator] = $value;
        }

        return $res;
    }
}
