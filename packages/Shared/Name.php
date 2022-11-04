<?php

declare(strict_types=1);

namespace Sigmie\Shared;

trait Name
{
    public readonly string $name;

    public function name(): string
    {
        return $this->name;
    }
}
