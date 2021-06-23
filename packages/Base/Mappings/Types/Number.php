<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Mappings\Type;

class Number extends Type
{
    protected string $type;

    public function integer()
    {
        $this->type = 'integer';

        return $this;
    }

    public function float()
    {
        $this->type = 'float';

        return $this;
    }

    public function raw()
    {
        return [
            'type' => $this->type,
        ];
    }
}
