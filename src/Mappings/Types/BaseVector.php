<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Types\Type as AbstractType;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\KnnVectorQuery;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Sigmie;

class BaseVector extends AbstractType
{
    public function __construct(
        public string $name,
        public readonly int $dims = 384,
        public readonly bool $index = true,
        public readonly VectorSimilarity $similarity = VectorSimilarity::Cosine,
        public readonly VectorStrategy $strategy = VectorStrategy::Concatenate,
        public readonly string $indexType = 'hnsw',
        public readonly ?int $m = 64,
        public readonly ?int $efConstruction = 300,
        public readonly ?float $confidenceInterval = null,
        public readonly ?int $oversample = null,
        public readonly ?string $apiName = null,
        public readonly ?string $boostedByField = null,
        public readonly bool $autoNormalizeVector = true,
    ) {
    }

    public function strategy(): VectorStrategy
    {
        return $this->strategy;
    }

    public function dims(): int
    {
        return $this->dims;
    }

    public function isIndexed(): bool
    {
        return $this->index;
    }

    public function similarity(): VectorSimilarity
    {
        return $this->similarity;
    }

    public function indexType(): string
    {
        return $this->indexType;
    }

    public function m(): ?int
    {
        return $this->m;
    }

    public function efConstruction(): ?int
    {
        return $this->efConstruction;
    }

    public function confidenceInterval(): ?float
    {
        return $this->confidenceInterval;
    }

    public function oversample(): ?int
    {
        return $this->oversample;
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

    public function queries(array|string $vector, ?\Sigmie\Base\Contracts\SearchEngine $driver = null, array $filter = []): array
    {
        if ($this->index && $driver) {
            return [
                $driver->knnQuery(
                    field: "_embeddings." . $this->fullPath,
                    queryVector: $vector,
                    k: $this->dims,
                    numCandidates: 300,
                    filter: $filter
                )
            ];
        }

        if ($this->index) {
            throw new \Exception('Driver is required for indexed vector queries');
        }

        // For exact vector search (accuracy 7), use function_score with dynamic similarity
        $source = match ($this->similarity) {
            VectorSimilarity::Cosine => "cosineSimilarity(params.query_vector, '_embeddings.{$this->fullPath}') + 1.0",
            VectorSimilarity::DotProduct => "dotProduct(params.query_vector, '_embeddings.{$this->fullPath}')",
            VectorSimilarity::Euclidean => "1 / (1 + l2norm(params.query_vector, '_embeddings.{$this->fullPath}'))",
            VectorSimilarity::MaxInnerProduct => "dotProduct(params.query_vector, '_embeddings.{$this->fullPath}')",
        };

        // Use bool query with filters if provided, otherwise use MatchAll
        if (!empty($filter)) {
            $baseQuery = new \Sigmie\Query\Queries\Compound\Boolean();
            $baseQuery->addRaw('filter', $filter);
        } else {
            $baseQuery = new MatchAll();
        }

        $query = [
            new FunctionScore(
                query: $baseQuery,
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
