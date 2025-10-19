<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\SearchEngine;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Type as AbstractType;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Sigmie;

class SigmieVector extends DenseVector
{
    public ?string $textFieldName = null;

    public ?string $apiName = null;

    public function __construct(
        public string $name,
        protected int $dims = 384,
        protected bool $index = true,
        protected VectorSimilarity $similarity = VectorSimilarity::Cosine,
        protected VectorStrategy $strategy = VectorStrategy::Concatenate,
        protected string $indexType = 'hnsw',
        protected ?int $m = 64,
        protected ?int $efConstruction = 300,
        protected ?float $confidenceInterval = null,
        protected ?int $oversample = null,
        ?string $apiName = null,
        protected ?string $boostedByField = null,
        protected bool $autoNormalizeVector = true,
    ) {
        $this->type = 'dense_vector';
        $this->apiName = $apiName;
    }

    public function toRaw(): array
    {
        if (Sigmie::$engine === SearchEngine::OpenSearch) {
            return $this->toOpenSearchRaw();
        }

        return $this->toElasticsearchRaw();
    }

    protected function toElasticsearchRaw(): array
    {
        $raw = [
            $this->name => [
                'type' => $this->type,
                'dims' => $this->dims,
                'index' => $this->index,
            ]
        ];

        if ($this->index) {
            $raw[$this->name]['similarity'] = $this->similarity->value;
            $raw[$this->name]['index_options'] = [
                'type' => $this->indexType,
                'm' => $this->m,
                'ef_construction' => $this->efConstruction,
            ];
        }

        if ($this->confidenceInterval !== null) {
            $raw['index_options']['confidence_interval'] = $this->confidenceInterval;
        }

        if ($this->oversample !== null) {
            $raw['index_options']['rescore_vector'] = [
                'oversample' => $this->oversample,
            ];
        }

        return $raw;
    }

    protected function toOpenSearchRaw(): array
    {
        $raw = [
            $this->name => [
                'type' => 'knn_vector',
                'dimension' => $this->dims,
            ]
        ];

        if ($this->index) {
            // Map Elasticsearch similarity to OpenSearch space_type
            $spaceType = match ($this->similarity) {
                VectorSimilarity::Cosine => 'cosinesimil',
                VectorSimilarity::Euclidean => 'l2',
                VectorSimilarity::DotProduct => 'innerproduct',
                VectorSimilarity::MaxInnerProduct => 'innerproduct',
            };

            $raw[$this->name]['method'] = [
                'name' => 'hnsw',
                'space_type' => $spaceType,
                'engine' => 'lucene',
                'parameters' => [
                    'm' => $this->m,
                    'ef_construction' => $this->efConstruction,
                ],
            ];
        }

        return $raw;
    }

    public function strategy(): VectorStrategy
    {
        return $this->strategy;
    }

    public function dims(): int
    {
        return $this->dims;
    }

    public function createSuffix(): string
    {
        if (!$this->index) {
            return 'exact_dims' . $this->dims . '_' . $this->similarity->value . '_' . $this->strategy->value;
        }

        $suffix = 'm' . $this->m . '_efc' . $this->efConstruction . '_dims' . $this->dims . '_' . $this->similarity->value . '_' . $this->strategy->value;

        return $suffix;
    }

    public function textFieldName(string $name): static
    {
        $this->textFieldName = $name;

        return $this;
    }

    public function embeddingsName(): string
    {
        return "{$this->textFieldName}.{$this->name}";
    }

    public function boostedByField(): ?string
    {
        return $this->boostedByField;
    }

    public function autoNormalizeVector(): bool
    {
        return $this->autoNormalizeVector;
    }

    public function similarity(): VectorSimilarity
    {
        return $this->similarity;
    }

    public function queries(array|string $vector): array
    {
        if ($this->index) {
            return [
                new NearestNeighbors(
                    "embeddings." . $this->fullPath,
                    $vector,
                    // // k: $this->dims,
                    // numCandidates: $this->efConstruction * 2
                    // Should be >= K
                    numCandidates: 300
                )
            ];
        }

        // For exact vector search (accuracy 7), use function_score with dynamic similarity
        $source = match ($this->similarity) {
            VectorSimilarity::Cosine => "cosineSimilarity(params.query_vector, 'embeddings.{$this->fullPath}') + 1.0",
            VectorSimilarity::DotProduct => "dotProduct(params.query_vector, 'embeddings.{$this->fullPath}')",
            VectorSimilarity::Euclidean => "1 / (1 + l2norm(params.query_vector, 'embeddings.{$this->fullPath}'))",
            VectorSimilarity::MaxInnerProduct => "dotProduct(params.query_vector, 'embeddings.{$this->fullPath}')",
        };

        $query = [
            new FunctionScore(
                query: new MatchAll(),
                source: $source,
                boostMode: 'replace',
                params: [
                    'query_vector' => $vector
                ]
            )
        ];

        return $query;
    }
}
