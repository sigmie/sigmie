<?php

declare(strict_types=1);

namespace Sigmie\Shared;

use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties as MappingsProperties;

trait Properties
{
    public MappingsProperties $properties;

    public function properties(MappingsProperties|NewProperties $props): static
    {
        $this->properties = $props instanceof NewProperties ? $props->get() : $props;

        if (count($this->fields) === 0) {
            $this->fields = array_keys($this->properties->toArray());
        }

        return $this;
    }
}
