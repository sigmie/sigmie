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

        // Set this Object_/Nested instance as the parent for all fields in properties
        $this->properties->propertiesParent($this);

        return $this;
    }
}
