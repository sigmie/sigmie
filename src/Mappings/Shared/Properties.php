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

        // Set paths for all fields based on this container's path
        $this->properties->setFieldPaths($this->fullPath());

        return $this;
    }
}
