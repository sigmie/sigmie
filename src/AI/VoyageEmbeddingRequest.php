<?php

declare(strict_types=1);

namespace Sigmie\AI;

use function Sigmie\Functions\batch_slots;
use function Sigmie\Functions\budgeted_embedding_batches;

trait VoyageEmbeddingRequest
{
    /**
     * @return iterable<array{dims: int|null, keys: list<int|string>, texts: list<string>}>
     */
    private function batchRequests(array $payload, int $maxInputs, ?string $inputType = null): iterable
    {
        $groups = batch_slots($payload, null, $maxInputs);
        $maxTokens = match ($this->model) {
            'voyage-4-lite', 'voyage-3.5-lite' => 1_000_000,
            'voyage-4', 'voyage-3.5', 'voyage-2' => 320_000,
            'voyage-4-large', 'voyage-3-large', 'voyage-code-3', 'voyage-finance-2',
            'voyage-law-2', 'voyage-large-2-instruct', 'voyage-multilingual-2' => 120_000,
            default => null,
        };

        if ($maxTokens === null) {
            return $groups;
        }

        $prefix = match ($inputType) {
            'query' => 'Represent the query for retrieving supporting documents: ',
            'document' => 'Represent the document for retrieval: ',
            default => '',
        };
        $legacy = str_ends_with($this->model, '-2') || $this->model === 'voyage-large-2-instruct';

        // Bound bytes after either NFC normalization or replacement of spaces with a three-byte marker.
        // Reserve room for the tokenizer's start/end markers and leading space.
        return budgeted_embedding_batches($groups, $maxTokens, static fn (string $text): int =>
            (strlen($text) + strlen($prefix)) * ($legacy || ! mb_check_encoding($text, 'ASCII') ? 3 : 1) + 5
        );
    }

    private function requestBody(string|array $input, ?int $dimensions, ?string $inputType = null): array
    {
        $body = ['model' => $this->model, 'input' => $input];

        if ($inputType !== null) {
            $body['input_type'] = $inputType;
        }

        if ($this->supportsDimensions($dimensions)) {
            $body['output_dimension'] = $dimensions;
        }

        return $body;
    }

    private function supportsDimensions(?int $dimensions): bool
    {
        return in_array($dimensions, [256, 512, 1024, 2048], true) && in_array($this->model, [
            'voyage-3-large', 'voyage-3.5', 'voyage-3.5-lite', 'voyage-code-3',
            'voyage-4', 'voyage-4-large', 'voyage-4-lite', 'voyage-code-4',
        ], true);
    }
}
