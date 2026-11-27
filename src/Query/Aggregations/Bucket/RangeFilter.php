<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

use Sigmie\Query\Queries\Term\Range as RangeQuery;

use function Sigmie\Functions\keyed;

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
        $range = (new RangeQuery($this->field, $this->ranges))->bounds();

        return [
            'filter' => [
                'range' => keyed($this->field, $range === [] ? (object) [] : $range),
            ],
        ];
    }
}
