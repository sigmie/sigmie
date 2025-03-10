<?php

declare(strict_types=1);

namespace Sigmie\Shared;

use Sigmie\Semantic\Contracts\AIProvider;

trait EmbeddingsProvider
{
    protected AIProvider $aiProvider;

    public function aiProvider(AIProvider $provider): static
    {
        $this->aiProvider = $provider;

        return $this;
    }

}
