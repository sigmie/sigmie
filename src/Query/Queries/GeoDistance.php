<?php

declare(strict_types=1);

namespace Sigmie\Query\Queries;

class GeoDistance extends Query
{
    public function __construct(
        protected string $field,
        protected string $distance,
        protected string|float $latitude,
        protected string|float $longitude,
    ) {}

    public function toRaw(): array
    {
        $point = [
            'lat' => $this->latitude,
            'lon' => $this->longitude,
        ];

        $clause = [
            'distance' => $this->distance,
            'boost' => $this->boost,
            // A search over several indices can reach one without this field.
            'ignore_unmapped' => true,
        ];

        // Dotted coordinates leave the scalar option keys available.
        if (array_key_exists($this->field, $clause)) {
            $clause[$this->field.'.lat'] = $this->latitude;
            $clause[$this->field.'.lon'] = $this->longitude;
        } else {
            $clause[$this->field] = $point;
        }

        return [
            'geo_distance' => $clause,
        ];
    }
}
