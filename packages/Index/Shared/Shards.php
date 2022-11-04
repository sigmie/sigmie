<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

trait Shards
{
    protected int $shards = 1;

    public function shards(int $shards): static
    {
        $this->shards = $shards;

        return $this;
    }
}
