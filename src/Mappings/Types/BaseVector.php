<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Type as AbstractType;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Query\Queries\Text\Nested;
use Sigmie\Sigmie;

class BaseVector extends AbstractType implements Type
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
        $this->apiName = $apiName;
    }

    public function toRaw(): array
    {
        // Return generic structure - driver will format using vectorField() strategy
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

            if ($this->confidenceInterval !== null) {
                $raw[$this->name]['index_options']['confidence_interval'] = $this->confidenceInterval;
            }

            if ($this->oversample !== null) {
                $raw[$this->name]['index_options']['rescore_vector'] = [
                    'oversample' => $this->oversample,
                ];
            }
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
