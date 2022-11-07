<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class Boolean extends Type
{
    public function toRaw(): array
    {
        return [$this->name => [
            'type' => 'boolean',
        ]];
    }
}
