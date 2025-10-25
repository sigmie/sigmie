<?php

declare(strict_types=1);

namespace Sigmie\Shared;

trait EmbeddingsProvider
{
    protected $aiProvider;

    public function aiProvider($provider): static
    {
        $this->aiProvider = $provider;

        return $this;
    }
}
