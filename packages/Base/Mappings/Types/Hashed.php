<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Contracts\Type;

class Hashed implements Type
{
    public function field(): string
    {
        return 'mapper-murmur3';
    }
}
