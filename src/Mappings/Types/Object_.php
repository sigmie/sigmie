<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class Object_ extends Type
{
    protected string $type = 'object';

    public function __construct(
        string $name,
    ) {
        parent::__construct($name);
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        return $queries;
    }
}
