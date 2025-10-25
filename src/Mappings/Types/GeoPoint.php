<?php

namespace Sigmie\Mappings\Types;

class GeoPoint extends Type
{
    protected string $type = 'geo_point';

    public function validate(string $key, mixed $value): array
    {
        if (isset($value['lat']) && isset($value['lon'])) {
            return [true, ''];
        }

        if (is_array($value)) {
            foreach ($value as $geoPoint) {

                if (! isset($geoPoint['lat']) || ! isset($geoPoint['lon'])) {
                    return [false, sprintf('The field %s mapped as %s must have lat and lon keys.', $key, $this->typeName())];

                }
            }

            return [true, ''];
        }

        return [false, sprintf('The field %s mapped as %s must have lat and lon keys.', $key, $this->typeName())];
    }
}
