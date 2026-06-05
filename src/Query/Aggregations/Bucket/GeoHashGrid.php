<?php

declare(strict_types=1);

namespace Sigmie\Query\Aggregations\Bucket;

class GeoHashGrid extends Bucket
{
    public function __construct(
        protected string $name,
        protected string $field,
        protected int $precision = 5,
        protected ?int $size = null,
    ) {
        parent::__construct($name);
    }

    protected function value(): array
    {
        $grid = [
            'field' => $this->field,
            'precision' => $this->precision,
        ];

        if ($this->size !== null) {
            $grid['size'] = $this->size;
        }

        return [
            'geohash_grid' => $grid,
        ];
    }
}
