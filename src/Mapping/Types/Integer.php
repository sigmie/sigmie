<?php

namespace Sigma\Mapping\Types;

use Sigma\Mapping\Type;

class Integer extends Type
{
    /**
     * Native field name
     *
     * @return string
     */
    public function field(): string
    {
        return 'integer';
    }
}

