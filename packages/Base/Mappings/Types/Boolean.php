<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Contracts\Type;

class Boolean implements Type
{
    public function field(): string
    {
        return 'boolean';
    }
}
