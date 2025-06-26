<?php

namespace Sigmie\Mappings\Types;

class GeoPoint extends Type
{
    protected string $type = 'geo_point';

    public function queries(array|string $queryString): array
    {
        return [];
    }

    public function validate(string $key, mixed $value): array
    {
        if (isset($value['lat']) && isset($value['lon'])) {

            return [true, ''];

        } elseif (is_array($value)) {

            foreach ($value as $geoPoint) {

                if (! isset($geoPoint['lat']) || ! isset($geoPoint['lon'])) {
                    return [false, "The field {$key} mapped as {$this->typeName()} must have lat and lon keys."];

                }
            }

            return [true, ''];

        } else {

            return [false, "The field {$key} mapped as {$this->typeName()} must have lat and lon keys."];
        }
    }
}
