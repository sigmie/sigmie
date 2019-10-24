<?php

namespace Sigma\Mapping\Types;

use Sigma\Mapping\Type;

class Hashed extends Type
{
    /**
     * Native field name
     *
     * @return string
     */
    public function field(): string
    {
        return 'mapper-murmur3';
    }
}
