<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Contracts\Type;

class IpAddress extends BaseType 
{
    public function field(): string
    {
        return 'ip';
    }
}
