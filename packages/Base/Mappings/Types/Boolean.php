<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Mappings\PropertyType;

class Boolean extends PropertyType
{
    public function toRaw(): array
    {
        return [$this->name => [
            'type' => 'boolean',
        ]];
    }
}
