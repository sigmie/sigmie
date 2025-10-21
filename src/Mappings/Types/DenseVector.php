<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Type as AbstractType;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\NearestNeighbors;

class DenseVector extends AbstractType implements Type
{
    public string $type = 'dense_vector';

    public ?string $textFieldName = null;

    public ?string $apiName = null;

    public ?string $boostedByField = null;

    public bool $autoNormalizeVector = true;

    public function __construct(
        public string $name,
        protected int $dims = 384,
        protected bool $index = true,
        protected VectorSimilarity $similarity = VectorSimilarity::Cosine,
        protected string $indexType = 'hnsw',
        protected ?int $m = 64,
        protected ?int $efConstruction = 300,
        protected ?float $confidenceInterval = null,
        protected ?int $oversample = null,
    ) {}

    public function toRaw(): array
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
        return VectorStrategy::Concatenate;
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
            return 'exact_dims' . $this->dims . '_' . $this->similarity->value . '_' . VectorStrategy::Concatenate->value;
        }

        return 'm' . $this->m . '_efc' . $this->efConstruction . '_dims' . $this->dims . '_' . $this->similarity->value . '_' . VectorStrategy::Concatenate->value;
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

    public function queries(array|string $vector, array $filter = []): array
    {
        return [
            new NearestNeighbors(
                field: '_embeddings.'.$this->fullPath,
                queryVector: $vector,
                k: $this->dims,
                numCandidates: $this->efConstruction * 2,
                filter: $filter,
                boost: 1.0,
            )
        ];
    }
}
