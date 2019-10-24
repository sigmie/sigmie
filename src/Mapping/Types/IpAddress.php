<?php

namespace Sigma\Mapping\Types;

use Sigma\Mapping\Type;

class Keyword extends Type
{
    public function field(): string
    {
        return 'ip';
    }
}
