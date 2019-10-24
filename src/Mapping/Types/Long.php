<?php

namespace Sigma\Mapping\Types;

use Sigma\Mapping\Type;

class Long extends Type
{
    /**
     * Native field name
     *
     * @return string
     */
    public function field(): string
    {
        return 'long';
    }
}
