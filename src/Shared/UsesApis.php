<?php

declare(strict_types=1);

namespace Sigmie\Shared;

use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\Contracts\RerankApi;

trait UsesApis
{
    public array $apis = [];

    public function apis(array $apis): static
    {
        $this->apis = $apis;

        return $this;
    }

    protected function getApi(?string $name = null): ?EmbeddingsApi
    {
        if ($name === null) {
            return null;
        }

        $api = $this->apis[$name] ?? null;

        return $api instanceof EmbeddingsApi ? $api : null;
    }

    protected function getRerankApi(?string $name = null): ?RerankApi
    {
        if ($name === null || $name === '') {
            return null;
        }

        $api = $this->apis[$name] ?? null;

        return $api instanceof RerankApi ? $api : null;
    }

    protected function hasApi(?string $name = null): bool
    {
        if ($name === null) {
            return ! empty($this->apis);
        }

        return isset($this->apis[$name]);
    }
}
