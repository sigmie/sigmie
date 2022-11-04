<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Type;

class Boolean extends Type
{
    public function toRaw(): array
    {
        return [$this->name => [
            'type' => 'boolean',
        ]];
    }
}
