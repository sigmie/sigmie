<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;

class Nested extends Type
{
    protected string $type = 'nested';

    public Properties $properties;

    public function __construct(
        string $name,
        Properties|NewProperties $properties = new NewProperties,
        string $parentPath = ''
    ) {
        parent::__construct($name);

        $this->parentPath = $parentPath;

        $this->properties($properties);
    }

    public function properties(Properties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        $this->properties->propertiesParent($props->fullPath, static::class);

        return $this;
    }

    public function toRaw(): array
    {
        $raw = parent::toRaw();

        $raw[$this->name]['properties'] = (object) $this->properties->toRaw();

        return $raw;
    }

    public function queries(array|string $queryString): array
    {
        $queries = [];

        return $queries;
    }

    public function validate(string $key, mixed $value): array
    {
        if (! is_array($value)) {
            return [false, "Nested field {$key} must be an object."];
        }

        if (count($value) === count($value, COUNT_RECURSIVE)) {
            return [false, "Nested field {$key} must be an array of objects."];
        }

        return [true, ''];
    }
}
