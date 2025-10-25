<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Parse\RangeOperatorParser;

class RangeFilter extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $field,
        protected array $ranges,
    ) {
        parent::__construct($name);
    }

    protected function value(): array
    {
        $res = [
            'filter' => [
                'range' => [],
            ],
        ];

        foreach ($this->ranges as $operator => $value) {
            $operator = (new RangeOperatorParser)->parse($operator);

            $res['filter']['range'][$this->field][$operator] = $value;
        }

        return $res;
    }
}
