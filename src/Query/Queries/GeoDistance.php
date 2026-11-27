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
        ];

        // Dotted coordinates leave the scalar distance and boost keys available.
        if ($this->field === 'distance' || $this->field === 'boost') {
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
