<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Contracts\Type;

class Number extends BaseType
{
    protected string $type;

    public function integer()
    {
        $this->type = 'integer';
    }

    public function float()
    {
        $this->type = 'float';
    }

    public function raw()
    {
        return [
                'type' => $this->type,
        ];
    }
}
