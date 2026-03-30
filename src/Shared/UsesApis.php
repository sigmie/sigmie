<?php

declare(strict_types=1);

namespace Sigmie\Shared;

use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\AI\Contracts\LLMApi;

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

        return $this->apis[$name] ?? null;
    }

    protected function getLlmApi(?string $name = null): ?LLMApi
    {
        if ($name === null || $name === '') {
            return null;
        }

        $api = $this->apis[$name] ?? null;

        return $api instanceof LLMApi ? $api : null;
    }

    protected function hasApi(?string $name = null): bool
    {
        if ($name === null) {
            return ! empty($this->apis);
        }

        return isset($this->apis[$name]);
    }
}
