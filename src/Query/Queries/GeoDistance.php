<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

use Sigmie\Query\Queries\Query;

class GeoDistance extends Query
{
    public function __construct(
        protected string $field,
        protected string $distance,
        protected string $latitude,
        protected string $longitude,
    ) {
    }

    public function toRaw(): array
    {
        return [
            'geo_distance' => [
                'distance' => $this->distance,
                $this->field => [
                    'lat' => $this->latitude,
                    'lon' => $this->longitude,
                ],
            ],
        ];
    }
}
