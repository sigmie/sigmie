<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

trait Shards
{
    protected int $shards = 1;

    protected bool $serverless = false;

    public function shards(int $shards): static
    {
        $this->shards = $shards;

        return $this;
    }

    public function serverless(bool $serverless = true): static
    {
        $this->serverless = $serverless;

        return $this;
    }
}
