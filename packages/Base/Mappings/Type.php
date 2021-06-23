<?php

declare(strict_types=1);

namespace Sigmie\Base\Mappings;

use Sigmie\Base\Contracts\Name as NameInterface;
use Sigmie\Base\Contracts\Type as TypeInterface;

abstract class Type implements TypeInterface, NameInterface
{
    protected string $type;

    public function __construct(protected string $name)
    {
    }

    public function __invoke()
    {
        return $this->raw();
    }

    public function type(): string
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    abstract protected function raw();
}
