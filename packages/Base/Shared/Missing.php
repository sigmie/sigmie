<?php

declare(strict_types=1);

namespace Sigmie\Base\Shared;

trait Missing
{
    protected int|string $missing;

    public function missing(int|string $value): self
    {
        $this->missing = $value;

        return $this;
    }
}
