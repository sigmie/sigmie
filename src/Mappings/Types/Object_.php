<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;

class Object_ extends Type
{
    protected string $type = 'object';

    public Properties $properties;

    public function __construct(
        string $name,
        Properties|NewProperties $properties = new NewProperties,
        ?string $fullPath = null,
    ) {
        parent::__construct(
            name: $name,
            fullPath: $fullPath
        );

        $this->properties($properties);
    }

    public function properties(Properties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        $parentName = $this->parentPath ? "{$this->parentPath}.{$this->name}" : $this->name;

        if (!$this->fullPath) {
            dd($this);
        }

        $this->properties->propertiesParent($parentName, static::class, $this->fullPath);

        return $this;
    }

    public function queries(array|string $queryString): array
    {
        $queries = [];

        return $queries;
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        $raw[$this->name]['properties'] = (object) $this->properties->toRaw();

        return $raw;
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_array($value)) {
            return [false, "Object field {$key} must be an object."];
        }

        if (count($value) === count($value, COUNT_RECURSIVE)) {
            return [false, "Onject field {$key} must be an array of objects."];
        }

        return [true, ''];
    }
}
