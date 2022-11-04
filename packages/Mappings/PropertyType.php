<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;
use Sigmie\Mappings\Contracts\Type;

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
