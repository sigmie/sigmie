<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;


class Boolean extends BaseType
{
    public function raw()
    {
        return [
            'type' => 'boolean',
        ];
    }
}
