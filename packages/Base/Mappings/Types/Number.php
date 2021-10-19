<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Mappings\PropertyType;

class Number extends PropertyType
{
    protected string $type;

    public function integer(): self
    {
        $this->type = 'integer';

        return $this;
    }

    public function float(): self
    {
        $this->type = 'float';

        return $this;
    }

    public function long(): self
    {
        $this->type = 'long';

        return $this;
    }

    public function toRaw(): array
    {
        return [$this->name => [
            'type' => $this->type,
        ]];
    }
}
