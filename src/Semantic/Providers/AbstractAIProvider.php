<?php

declare(strict_types=1);

namespace Sigmie\Semantic\Providers;

abstract class AbstractAIProvider
{
    public function rerank(array $documents, string $queryString): array
    {
        // This function should return an associative array mapping the index of each document
        // to a relevance score between 0 and 1. The relevance score indicates how relevant
        // each document is to the given query string, with higher scores meaning higher relevance.
        // For example:
        // [
        //   1 => 0.73046875,  // Document at index 1 has a relevance score of 0.73046875
        //   2 => 0.67578125,  // Document at index 2 has a relevance score of 0.67578125
        //   3 => 0.3828125,   // Document at index 3 has a relevance score of 0.3828125
        //   0 => 0.306640625  // Document at index 0 has a relevance score of 0.306640625
        // ]
        return [];
    }

    public function batchEmbed(array $payload): array
    {
        $embeddings = [];

        foreach ($payload as $item) {
            $embeddings[] = $this->embed($item);
        }

        return $embeddings;
    }

    public function threshold(): float
    {
        return 0;
    }
}
