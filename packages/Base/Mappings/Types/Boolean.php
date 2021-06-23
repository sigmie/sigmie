<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Mappings\Type;

class Boolean extends Type
{
    public function raw()
    {
        return [
            'type' => 'boolean',
        ];
    }
}
