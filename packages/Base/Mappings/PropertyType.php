<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use Sigmie\Base\Contracts\Name;
use Sigmie\Base\Contracts\ToRaw;
use Sigmie\Base\Contracts\Type;

abstract class PropertyType implements Name, ToRaw, Type
{
    protected string $type;

    public function __construct(protected string $name)
    {
    }

    public function __invoke(): array
    {
        return $this->toRaw();
    }

    public function type(): string
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    abstract public function toRaw(): array;
}
