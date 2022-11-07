<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class Keyword extends Type
{
    public function toRaw(): array
    {
        return [$this->name => [
            'type' => 'keyword',
        ]];
    }
}
