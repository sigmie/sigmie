<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings\Types;

use Sigmie\Base\Contracts\Type;

abstract class BaseType implements Type
{
    public function __construct(protected string $name)
    {
    }

    public function __invoke()
    {
        return $this->raw();
    }

    public function name(): string
    {
        return $this->name;
    }

    abstract protected function raw();
}
