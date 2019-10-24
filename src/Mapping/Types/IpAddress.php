<?php

namespace Sigma\Mapping\Types;

use Sigma\Mapping\Type;

class IpAddress extends Type
{
    public function field(): string
    {
        return 'ip';
    }
}
