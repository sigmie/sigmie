<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Mappings\Contracts\Type as TypeInterface;
use Sigmie\Shared\Contracts\ToRaw;
use Sigmie\Shared\Contracts\Name;

abstract class Type implements Name, ToRaw, TypeInterface
{
    protected string $type;

    public function __construct(
        public readonly string $name
    ) {
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
