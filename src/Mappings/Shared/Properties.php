<?php

namespace Sigmie\Mappings\Shared;

use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties as MappingsProperties;

trait Properties
{
    public MappingsProperties $properties;

    public function properties(MappingsProperties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        $this->properties->propertiesParent($props->fullPath, static::class);

        return $this;
    }
}
