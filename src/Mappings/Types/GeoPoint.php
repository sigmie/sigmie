<?php

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Types\Type;

class GeoPoint extends Type
{
    protected string $type = 'geo_point';

    public function queries(string $queryString): array
    {
        return [];
    }

    public function validate(string $key, mixed $value): array
    {
        if (isset($value['lat']) && isset($value['lon'])) {

            return [true, ''];

        } elseif (is_array($value)) {

            foreach ($value as $geoPoint) {

                if (!isset($geoPoint['lat']) || !isset($geoPoint['lon'])) {
                    return [false, "GeoPoint field {$key} must have lat and lon keys."];

                }
            }

            return [true, ''];

        } else {

            return [false, "GeoPoint field {$key} must have lat and lon keys."];
        }
    }
}
