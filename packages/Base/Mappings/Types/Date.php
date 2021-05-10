<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Contracts\Type;

class Date extends BaseType 
{
    public function field(): string
    {
        return 'date';
    }
}
