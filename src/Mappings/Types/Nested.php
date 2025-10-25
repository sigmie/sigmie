<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Contracts\PropertiesField;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Shared\Properties as SharedProperties;

class Nested extends Type implements PropertiesField
{
    use SharedProperties;

    protected string $type = 'nested';

    public function __construct(
        string $name,
        Properties|NewProperties $properties = new NewProperties,
        ?string $fullPath = '',
    ) {
        parent::__construct(
            name: $name,
            fullPath: $fullPath
        );

        $this->properties($properties);
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        $raw[$this->name]['properties'] = (object) $this->properties->toRaw();

        return $raw;
    }

    public function queries(array|string $queryString): array
    {
        return [];
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_array($value)) {
            return [false, sprintf('Nested field %s must be an object.', $key)];
        }

        if (count($value) === count($value, COUNT_RECURSIVE)) {
            return [false, sprintf('Nested field %s must be an array of objects.', $key)];
        }

        return [true, ''];
    }
}
