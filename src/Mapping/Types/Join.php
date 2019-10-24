<?php

namespace Sigma\Mapping\Types;

use Sigma\Mapping\Type;

class Join extends Type
{
    /**
     * Native field name
     *
     * @return string
     */
    public function field(): string
    {
        return 'join';
    }
}
