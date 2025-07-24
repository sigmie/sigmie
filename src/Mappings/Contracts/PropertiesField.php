<?php

namespace Sigmie\Mappings\Contracts;

use Sigmie\Mappings\Properties;

interface PropertiesField
{
    public function properties(Properties $properties): self;
}
