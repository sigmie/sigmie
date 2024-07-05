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
}
