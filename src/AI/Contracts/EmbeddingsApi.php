<?php

declare(strict_types=1);

namespace Sigmie\AI\Contracts;

use GuzzleHttp\Promise\Promise;

interface EmbeddingsApi
{
    /**
     * Generate embeddings for a single text
     */
    public function embed(string $text, int $dimensions): array;

    /**
     * Generate embeddings for multiple texts in batch
     */
    public function batchEmbed(array $payload): array;

    /**
     * Generate embeddings asynchronously
     */
    public function promiseEmbed(string $text, int $dimensions): Promise;

    public function model(): string;
}
