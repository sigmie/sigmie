<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Support\VectorMath;

use function Sigmie\Functions\response_value;

class MMR
{
    public function __construct(
        protected float $lambda = 0.5
    ) {}

    /**
     * Apply Maximal Marginal Relevance to diversify results
     *
     * @param  array  $hits  Array of hits with their vectors
     * @param  array  $seedDocs  Seed documents to extract query vectors from
     * @param  string  $fieldName  Field name containing vectors in embeddings
     * @param  int  $topK  Number of diversified results to return
     * @return array Diversified results
     */
    public function diversify(array $hits, array $seedDocs, string $fieldName, int $topK): array
    {
        if ($hits === [] || $seedDocs === [] || $fieldName === '') {
            return array_slice($hits, 0, $topK);
        }

        $seeds = [];
        foreach ($seedDocs as $doc) {
            foreach (self::documentVectors($doc, $fieldName) as $space => $vectors) {
                foreach ($vectors as $vector) {
                    $seeds[$space][count($vector)][] = $vector;
                }
            }
        }

        if ($seeds === []) {
            return array_slice($hits, 0, $topK);
        }

        $centroids = array_map(fn (array $byDimension): array => array_map(fn (array $vectors): array => [VectorMath::centroid($vectors)], $byDimension), $seeds);
        $selectedVectors = [];
        $selected = [];
        $remaining = $hits;

        while (count($selected) < $topK && $remaining !== []) {
            $maxScore = -INF;
            $maxIndex = null;

            foreach ($remaining as $index => $hit) {
                $docVectors = self::documentVectors($hit, $fieldName);

                $relevance = $this->maxSimilarity($docVectors, $centroids);
                if ($relevance === null) {
                    continue;
                }

                $maxSimilarity = $this->maxSimilarity($docVectors, $selectedVectors) ?? 0.0;

                // MMR score: λ * relevance - (1-λ) * max_similarity
                $mmrScore = ($this->lambda * $relevance) - ((1 - $this->lambda) * $maxSimilarity);

                if ($mmrScore > $maxScore) {
                    $maxScore = $mmrScore;
                    $maxIndex = $index;
                }
            }

            if ($maxIndex !== null) {
                $selected[] = $remaining[$maxIndex];
                foreach (self::documentVectors($remaining[$maxIndex], $fieldName) as $space => $vectors) {
                    foreach ($vectors as $vector) {
                        $selectedVectors[$space][count($vector)][] = $vector;
                    }
                }

                unset($remaining[$maxIndex]);
                $remaining = array_values($remaining);
            } else {
                // No more hits with valid vectors - fill remaining with whatever's left
                while (count($selected) < $topK && $remaining !== []) {
                    $selected[] = array_shift($remaining);
                }

                // @codeCoverageIgnoreStart
                break;
                // @codeCoverageIgnoreEnd
            }
        }

        return $selected;
    }

    /**
     * Accuracy 1-6 stores a float list. Accuracy 7 stores {"vector": <embedding>}.
     *
     * @return list<list<int|float>>
     */
    public static function storedVectors(mixed $stored): array
    {
        if (! is_array($stored) || $stored === []) {
            return [];
        }

        if (array_is_list($stored) && is_numeric(reset($stored))) {
            return [$stored];
        }

        if (isset($stored['vector']) && is_array($stored['vector'])) {
            return self::storedVectors($stored['vector']);
        }

        $vectors = [];

        foreach ($stored as $value) {
            if (is_array($value)) {
                $vectors = [...$vectors, ...self::storedVectors($value)];
            }
        }

        return $vectors;
    }

    private function maxSimilarity(array $left, array $right): ?float
    {
        $best = null;
        foreach ($left as $space => $vectors) {
            foreach ($vectors as $vector) {
                foreach ($right[$space][count($vector)] ?? [] as $other) {
                    $cosine = VectorMath::cosineSimilarity($vector, $other);
                    $best = $best === null ? $cosine : max($best, $cosine);
                }
            }
        }

        return $best;
    }

    /** @return array<string, list<list<int|float>>> Vectors grouped by mapped vector field. */
    public static function documentVectors(mixed $hit, string $fieldName): array
    {
        $source = is_array($hit) ? ($hit['_source'] ?? []) : ($hit->_source ?? []);
        if (! is_array($source) || ! is_array($source['_embeddings'] ?? null)) {
            return [];
        }

        $stored = response_value($source['_embeddings'], $fieldName);

        if (! is_array($stored)) {
            return [];
        }

        if (array_is_list($stored) || isset($stored['vector'])) {
            $stored = ['' => $stored];
        }

        return array_filter(array_map(self::storedVectors(...), $stored));
    }
}
