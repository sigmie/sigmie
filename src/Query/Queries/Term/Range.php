<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries\Term;

use Exception;
use Sigmie\Parse\RangeOperatorParser;
use Sigmie\Query\Queries\Query;

class Range extends Query
{
    /**
     * values example: ['>=' => '2','<=' => '200']
     */
    public function __construct(
        protected string $field,
        protected array $values = [],
    ) {}

    public function toRaw(): array
    {
        $res = [
            'range' => [
                $this->field => [],
            ],
        ];

        foreach ($this->values as $operator => $value) {

            $operator = (new RangeOperatorParser)->parse($operator);

            $res['range'][$this->field][$operator] = $value;
        }

        return $res;
    }
}
