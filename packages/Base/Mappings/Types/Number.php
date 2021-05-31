<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;


class Number extends BaseType
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
