<?php

declare(strict_types=1);

namespace Sigmie\Base\Shared;

trait Meta
{
    protected array $meta = [];

    public function meta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }
}
