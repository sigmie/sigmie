<?php

declare(strict_types=1);

namespace Sigmie\Shared;

use Sigmie\AI\Contracts\EmbeddingsApi;

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

    protected function hasApi(?string $name = null): bool
    {
        if ($name === null) {
            return !empty($this->apis);
        }

        return isset($this->apis[$name]);
    }
}
