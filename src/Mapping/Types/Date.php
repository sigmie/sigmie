<?php

namespace Sigma\Mapping\Types;

use Sigma\Mapping\Type;

class Date extends Type
{
    public function field(): string
    {
        return 'date';
    }
}
