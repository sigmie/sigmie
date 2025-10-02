<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\AI\Contracts\EmbeddingsApi;

class VectorPool
{
    protected array $pool = [];

    public function __construct(
        protected EmbeddingsApi $embeddingsApi
    ) {}

    public function get(string $text, int $dimensions): array
    {
        // If not in pool, generate and cache
        if (!isset($this->pool[$text][$dimensions])) {
            if (!isset($this->pool[$text])) {
                $this->pool[$text] = [];
            }

            $this->pool[$text][$dimensions] = $this->embeddingsApi->embed($text, $dimensions);
        }

        return $this->pool[$text][$dimensions];
    }

    public function getMany(array $items): static
    {
        // $items format: [['text' => 'foo', 'dims' => 256], ...]
        // Filter out items that are already in the pool
        $missing = array_filter($items, fn($item) => !$this->has($item['text'], $item['dims']));

        if (empty($missing)) {
            return $this;
        }

        // Use batchEmbed to generate all missing embeddings at once
        $results = $this->embeddingsApi->batchEmbed($missing);

        // Store results in pool
        foreach ($results as $result) {
            $text = $result['text'];
            $dims = (int) $result['dims'];
            $vector = $result['vector'];

            if (!isset($this->pool[$text])) {
                $this->pool[$text] = [];
            }

            $this->pool[$text][$dims] = $vector;
        }

        return $this;
    }

    public function has(string $text, int $dimensions): bool
    {
        return isset($this->pool[$text][$dimensions]);
    }

    public function getPool(): array
    {
        return $this->pool;
    }

    public function setPool(array $pool): static
    {
        foreach ($pool as $text => $dimensions) {
            if (!isset($this->pool[$text])) {
                $this->pool[$text] = [];
            }

            foreach ($dimensions as $dim => $vector) {
                // Only add if not already present
                if (!isset($this->pool[$text][$dim])) {
                    $this->pool[$text][$dim] = $vector;
                }
            }
        }

        return $this;
    }
}
