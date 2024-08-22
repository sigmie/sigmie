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

        $this->properties->setParentName($this->name);

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
}
