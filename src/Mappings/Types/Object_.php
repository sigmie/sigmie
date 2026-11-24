<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Contracts\FieldContainer;
use Sigmie\Mappings\Contracts\PropertiesField;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Shared\Properties as SharedProperties;

class Object_ extends Type implements FieldContainer, PropertiesField
{
    use SharedProperties;

    protected string $type = 'object';

    public function isEnabled(): bool
    {
        return $this->mappingOptions['enabled'] ?? true;
    }

    public function queries(array|string $queryString): array
    {
        return [];
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }
}
