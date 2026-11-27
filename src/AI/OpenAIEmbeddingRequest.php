<?php

declare(strict_types=1);

namespace Sigmie\AI;

use InvalidArgumentException;

use function Sigmie\Functions\batch_slots;
use function Sigmie\Functions\budgeted_embedding_batches;

trait OpenAIEmbeddingRequest
{
    private function batchInputLimit(): int
    {
        return 2048;
    }

    /**
     * @return iterable<array{dims: int|null, keys: list<int|string>, texts: list<string>}>
     */
    private function batchRequests(array $payload, int $maxInputs): iterable
    {
        // Byte-based tokenization emits at most one token per byte, capped at 8192 for valid inputs.
        return budgeted_embedding_batches(
            batch_slots($payload, $this->defaultDimensions(), $maxInputs),
            300_000,
            static fn (string $text): int => min(strlen($text), 8192),
        );
    }

    private function defaultDimensions(): int
    {
        return $this->model === 'text-embedding-3-large' ? 3072 : 1536;
    }

    private function requestBody(string|array $input, ?int $dimensions): array
    {
        $body = ['model' => $this->model, 'input' => $input];

        $dimensions = $this->requestDimensions($dimensions);

        return $dimensions === null ? $body : [...$body, 'dimensions' => $dimensions];
    }

    private function requestDimensions(?int $dimensions): ?int
    {
        if ($dimensions === null) {
            return null;
        }

        if ($this->model === 'text-embedding-ada-002') {
            if ($dimensions !== 1536) {
                throw new InvalidArgumentException('text-embedding-ada-002 requires 1536 dimensions.');
            }

            return null;
        }

        if ($dimensions < 1) {
            throw new InvalidArgumentException('OpenAI embedding dimensions must be positive.');
        }

        if (in_array($this->model, ['text-embedding-3-small', 'text-embedding-3-large'], true)
            && $dimensions > $this->defaultDimensions()) {
            throw new InvalidArgumentException(sprintf('%s supports at most %s dimensions.', $this->model, $this->defaultDimensions()));
        }

        return $dimensions;
    }
}
