<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\AI\APIs\CohereEmbeddingsApi;
use Sigmie\AI\APIs\VoyageEmbeddingsApi;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Support\VectorMath;
use UnexpectedValueException;

class VectorPool
{
    protected array $pool = [];

    public function __construct(protected EmbeddingsApi $embeddingsApi, protected bool $ensureNormalized = true)
    {
        if ($embeddingsApi instanceof CohereEmbeddingsApi) {
            $this->embeddingsApi = $embeddingsApi->forSearchQueries();
        }
    }

    public function get(string $text, int $dimensions): array
    {
        // If not in pool, generate and cache
        if (! isset($this->pool[$text][$dimensions])) {
            if (! isset($this->pool[$text])) {
                $this->pool[$text] = [];
            }

            $vector = $this->embeddingsApi instanceof VoyageEmbeddingsApi
                ? $this->embeddingsApi->embedQuery($text, $dimensions)
                : $this->embeddingsApi->embed($text, $dimensions);

            $this->pool[$text][$dimensions] = $this->prepareVector($vector);
        }

        return $this->pool[$text][$dimensions];
    }

    public function getMany(array $items): static
    {
        $missing = [];
        $seen = [];
        foreach ($items as $item) {
            $text = $item['text'];
            $dims = $item['dims'];
            if ($this->has($text, $dims)) {
                continue;
            }

            if (isset($seen[$text][$dims])) {
                continue;
            }

            $missing[] = $item;
            $seen[$text][$dims] = true;
        }

        if ($missing === []) {
            return $this;
        }

        foreach (array_chunk($missing, $this->embeddingsApi->maxBatchSize()) as $batch) {
            $results = $this->embeddingsApi instanceof VoyageEmbeddingsApi
                ? $this->embeddingsApi->batchEmbedQueries($batch)
                : $this->embeddingsApi->batchEmbed($batch);
            if (count($results) !== count($batch)) {
                throw new UnexpectedValueException('The query embedding batch returned the wrong number of vectors.');
            }

            $vectors = [];
            foreach ($results as $result) {
                $vectors[$result['text']][(int) $result['dims']] = $result['vector'];
            }

            foreach ($batch as $item) {
                if (! isset($vectors[$item['text']][$item['dims']])) {
                    throw new UnexpectedValueException('The query embedding batch omitted a requested vector.');
                }
            }

            $this->setPool($vectors);
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
            if (! isset($this->pool[$text])) {
                $this->pool[$text] = [];
            }

            foreach ($dimensions as $dim => $vector) {
                // Only add if not already present
                if (! isset($this->pool[$text][$dim])) {
                    $this->pool[$text][$dim] = $this->prepareVector($vector);
                }
            }
        }

        return $this;
    }

    private function prepareVector(array $vector): array
    {
        return $this->ensureNormalized && ! VectorMath::isNormalized($vector)
            ? VectorMath::normalize($vector)
            : $vector;
    }
}
