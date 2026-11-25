<?php

declare(strict_types=1);

namespace Sigmie\AI\APIs;

use Psr\Http\Message\ResponseInterface;
use stdClass;
use UnexpectedValueException;

final class ApiResponse
{
    public static function embeddings(ResponseInterface $response, int $expected): array
    {
        return self::indexedEmbeddings(self::json($response)->data ?? null, $expected);
    }

    public static function jinaEmbeddings(ResponseInterface $response, int $expected): array
    {
        $body = self::json($response);
        $status = $body->header->status ?? null;
        if (in_array($status->code ?? null, ['ERROR', 1], true)) {
            $description = $status->description ?? null;
            throw new UnexpectedValueException(is_string($description) && $description !== '' ? $description : 'Jina executor failed.');
        }

        return self::indexedEmbeddings($body->data ?? null, $expected);
    }

    public static function cohereEmbeddings(ResponseInterface $response, int $expected): array
    {
        $body = self::json($response);
        $rows = self::embeddingRows($body->embeddings->float ?? null, $expected);

        return array_map(self::vector(...), $rows);
    }

    public static function rerank(ResponseInterface $response, string $field, int $inputCount): array
    {
        $body = self::json($response);
        $rows = $body->{$field} ?? null;

        if (! is_array($rows)) {
            throw new UnexpectedValueException('Rerank response is missing the result list.');
        }

        $results = array_map(static fn ($row): array => [
            'index' => $row->index ?? null,
            'score' => $row->relevance_score ?? null,
        ], $rows);

        self::validateRanking($results, $inputCount);

        return $results;
    }

    public static function validateRanking(array $results, int $inputCount): void
    {
        $seen = [];
        foreach ($results as $result) {
            $index = $result['index'] ?? null;
            $score = $result['score'] ?? null;

            if (! is_int($index) || $index < 0 || $index >= $inputCount || isset($seen[$index])) {
                throw new UnexpectedValueException('Rerank response contains an invalid or duplicate document index.');
            }

            if ((! is_int($score) && ! is_float($score)) || ! is_finite($score)) {
                throw new UnexpectedValueException('Rerank response contains an invalid relevance score.');
            }

            $seen[$index] = true;
        }
    }

    public static function resizeEmbedding(array $embedding, ?int $dimensions): array
    {
        if ($dimensions === null || $dimensions <= 0 || count($embedding) === $dimensions) {
            return $embedding;
        }

        return count($embedding) > $dimensions
            ? array_slice($embedding, 0, $dimensions)
            : array_pad($embedding, $dimensions, 0.0);
    }

    private static function json(ResponseInterface $response): stdClass
    {
        $body = json_decode((string) $response->getBody(), flags: JSON_THROW_ON_ERROR);

        if (! $body instanceof stdClass) {
            throw new UnexpectedValueException('AI API response must contain a JSON object.');
        }

        return $body;
    }

    private static function indexedEmbeddings(mixed $data, int $expected): array
    {
        $vectors = [];
        foreach (self::embeddingRows($data, $expected) as $offset => $row) {
            $index = $row->index ?? $offset;

            if (! is_int($index) || $index < 0 || $index >= $expected || isset($vectors[$index])) {
                throw new UnexpectedValueException('Embedding response contains an invalid or duplicate input index.');
            }

            $vectors[$index] = self::vector($row->embedding ?? null);
        }

        ksort($vectors);

        return $vectors;
    }

    private static function embeddingRows(mixed $rows, int $expected): array
    {
        if (! is_array($rows) || count($rows) !== $expected) {
            throw new UnexpectedValueException(sprintf('Embedding response must contain %d vector(s).', $expected));
        }

        return $rows;
    }

    private static function vector(mixed $vector): array
    {
        if (is_string($vector)) {
            $bytes = base64_decode($vector, true);
            if ($bytes === false || $bytes === '' || strlen($bytes) % 4 !== 0) {
                throw new UnexpectedValueException('Embedding response contains an invalid base64 vector.');
            }

            $vector = array_values(unpack('g*', $bytes));
        }

        if (! is_array($vector) || $vector === []) {
            throw new UnexpectedValueException('Embedding response contains an invalid vector.');
        }

        foreach ($vector as $component) {
            if ((! is_int($component) && ! is_float($component)) || ! is_finite($component)) {
                throw new UnexpectedValueException('Embedding response contains an invalid vector component.');
            }
        }

        return $vector;
    }
}
