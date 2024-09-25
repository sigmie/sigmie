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
        NewProperties $properties = new NewProperties
    ) {
        parent::__construct($name);

        $this->properties($properties);
    }

    public function properties(Properties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        $parentName = $this->parentPath ? "{$this->parentPath}.{$this->name}" : $this->name;

        $this->properties->propertiesParent($parentName, static::class);

        return $this;
    }

    public function queries(string $queryString): array
    {
        $queries = [];

        return $queries;
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        $raw[$this->name]['properties'] = $this->properties->toRaw();

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
