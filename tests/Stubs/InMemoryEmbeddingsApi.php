<?php

declare(strict_types=1);

namespace Sigmie\Tests\Stubs;

use GuzzleHttp\Promise\Promise;
use RuntimeException;
use Sigmie\AI\Contracts\EmbeddingsApi;

class InMemoryEmbeddingsApi implements EmbeddingsApi
{
    public function __construct(
        protected int $dims = 384,
        protected int $batchCap = 100,
        protected string $name = 'in-memory',
    ) {}

    public function embed(string $text, int $dimensions): array
    {
        return $this->vectorFor($text, $dimensions ?: $this->dims);
    }

    public function batchEmbed(array $payload): array
    {
        foreach ($payload as $i => $item) {
            $dims = (int) ($item['dims'] ?? $this->dims);
            $payload[$i]['vector'] = $this->vectorFor((string) ($item['text'] ?? ''), $dims);
        }

        return $payload;
    }

    public function promiseEmbed(string $text, int $dimensions): Promise
    {
        throw new RuntimeException('promiseEmbed is not used in batch embeddings tests');
    }

    public function model(): string
    {
        return $this->name;
    }

    public function maxBatchSize(): int
    {
        return $this->batchCap;
    }

    protected function vectorFor(string $text, int $dims): array
    {
        $seed = crc32($text);
        $out = [];
        for ($i = 0; $i < $dims; $i++) {
            $out[] = (float) ((($seed + $i) % 1000) / 1000);
        }

        return $out;
    }
}
