<?php

namespace Sigma\Mapping\Types;

use Sigma\Mapping\Type;

class Text extends Type
{
    public function field(): string
    {
        return 'text';
    }
}
