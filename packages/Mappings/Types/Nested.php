<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\NewProperties;
use Sigmie\Shared\Properties;

class Nested extends Type
{
    protected string $type = 'nested';

    use Properties;

    public function __construct(
        string $name,
        NewProperties $properties = new NewProperties
    ) {
        parent::__construct($name);

        $this->properties($properties);
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        $raw[$this->name]['properties'] = $this->properties->toRaw();

        return $raw;
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        return $queries;
    }
}
