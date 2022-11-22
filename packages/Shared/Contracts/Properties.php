<?php

declare(strict_types=1);

namespace Sigmie\Shared\Contracts;

use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties as MappingsProperties;

interface Properties
{
    public function properties(MappingsProperties|NewProperties $properties): static;
}
