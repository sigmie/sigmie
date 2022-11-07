<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Types\Type;

class Keyword extends Type
{
    public function toRaw(): array
    {
        return [$this->name => [
            'type' => 'keyword',
        ]];
    }
}
