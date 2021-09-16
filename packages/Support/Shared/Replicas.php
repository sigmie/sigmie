<?php

declare(strict_types=1);

namespace Sigmie\Support\Shared;



trait Replicas
{
    protected int $replicas = 2;

    public function replicas(int $replicas): static
    {
        $this->replicas = $replicas;

        return $this;
    }
}
