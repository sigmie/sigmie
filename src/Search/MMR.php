<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\Support\VectorMath;

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
        if ($hits === [] || $seedDocs === [] || ($fieldName === '' || $fieldName === '0')) {
            return array_slice($hits, 0, $topK);
        }

        // Extract query vectors from seed documents
        $queryVectors = $this->extractQueryVectors($seedDocs, $fieldName);

        if ($queryVectors === []) {
            return array_slice($hits, 0, $topK);
        }

        // Calculate centroid of query vectors for relevance comparison
        $queryVector = VectorMath::centroid($queryVectors);

        if ($queryVector === []) {
            return array_slice($hits, 0, $topK);
        }

        $selected = [];
        $remaining = $hits;

        while (count($selected) < $topK && $remaining !== []) {
            $maxScore = -INF;
            $maxIndex = null;

            foreach ($remaining as $index => $hit) {
                $docVector = $this->extractVector($hit, $fieldName);

                if ($docVector === null) {
                    continue;
                }

                // Calculate relevance (similarity to query)
                $relevance = VectorMath::cosineSimilarity($queryVector, $docVector);

                // Calculate diversity (max similarity to already selected docs)
                $maxSimilarity = 0.0;

                foreach ($selected as $selectedHit) {
                    $selectedVector = $this->extractVector($selectedHit, $fieldName);

                    if ($selectedVector === null) {
                        continue;
                    }

                    $similarity = VectorMath::cosineSimilarity($docVector, $selectedVector);
                    $maxSimilarity = max($maxSimilarity, $similarity);
                }

                // MMR score: λ * relevance - (1-λ) * max_similarity
                $mmrScore = ($this->lambda * $relevance) - ((1 - $this->lambda) * $maxSimilarity);

                if ($mmrScore > $maxScore) {
                    $maxScore = $mmrScore;
                    $maxIndex = $index;
                }
            }

            if ($maxIndex !== null) {
                $selected[] = $remaining[$maxIndex];
                unset($remaining[$maxIndex]);
                $remaining = array_values($remaining);
            } else {
                // No more hits with valid vectors - fill remaining with whatever's left
                while (count($selected) < $topK && $remaining !== []) {
                    $selected[] = array_shift($remaining);
                }

                break;
            }
        }

        return $selected;
    }

    /**
     * Extract query vectors from seed documents
     */
    protected function extractQueryVectors(array $seedDocs, string $fieldName): array
    {
        $queryVectors = [];

        foreach ($seedDocs as $doc) {
            $source = is_array($doc) ? ($doc['_source'] ?? null) : ($doc->_source ?? null);
            if (! $source) {
                continue;
            }

            if (! isset($source['_embeddings'])) {
                continue;
            }

            // Get all vectors for the field using dot notation
            $vectors = dot($source['_embeddings'])->get($fieldName);
            if (! $vectors) {
                continue;
            }

            if (! is_array($vectors)) {
                continue;
            }

            // Add all vectors from this document
            foreach ($vectors as $vector) {
                if (is_array($vector) && $vector !== []) {
                    $firstElement = reset($vector);
                    if (is_numeric($firstElement)) {
                        $queryVectors[] = $vector;
                    }
                }
            }
        }

        return $queryVectors;
    }

    /**
     * Extract vector from hit's embeddings field
     */
    protected function extractVector($hit, string $fieldName): ?array
    {
        $source = is_array($hit) ? ($hit['_source'] ?? null) : ($hit->_source ?? null);

        if (! $source || ! isset($source['_embeddings'])) {
            return null;
        }

        // Get all vectors for the field using dot notation (same as NewRecommendations)
        $vectors = dot($source['_embeddings'])->get($fieldName);

        if (! $vectors || ! is_array($vectors)) {
            return null;
        }

        // Get the first valid vector (should be consistent across all documents)
        foreach ($vectors as $vectorData) {
            if (! is_array($vectorData)) {
                continue;
            }

            if ($vectorData === []) {
                continue;
            }

            // Check if it's a numeric vector array
            $firstElement = reset($vectorData);
            if (is_numeric($firstElement)) {
                return $vectorData;
            }
        }

        return null;
    }
}
