<?php

declare(strict_types=1);

namespace Sigmie\Shared;

use Sigmie\Semantic\Contracts\Provider;

trait EmbeddingsProvider
{
    protected Provider $embeddingsProvider;

    public function embeddingsProvider(Provider $provider): static
    {
        $this->embeddingsProvider = $provider;

        return $this;
    }

}
