<?php

declare(strict_types=1);

namespace Sigmie\Base;

trait Name
{
    protected string $name;

    public function name(): string
    {
        return $this->name;
    }
}
