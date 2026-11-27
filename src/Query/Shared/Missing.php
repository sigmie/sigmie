<?php

declare(strict_types=1);

namespace Sigmie\Query\Shared;

trait Missing
{
    protected int|float|string|bool $missing;

    public function missing(int|float|string|bool $value): self
    {
        $this->missing = $value;

        return $this;
    }
}
