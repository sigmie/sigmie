<?php

declare(strict_types=1);

namespace Sigmie\Base\Mapping\Types;

use Sigmie\Base\Contracts\Type;

class Text implements Type
{
    public function field(): string
    {
        return 'text';
    }
}
