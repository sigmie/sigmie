<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Type as AbstractType;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\OpenSearchKnn;

class KnnVector extends AbstractType implements Type
{
    public string $type = 'knn_vector';

    public ?string $textFieldName = null;

    public ?string $apiName = null;

    public ?string $boostedByField = null;

    public bool $autoNormalizeVector = true;

    public function __construct(
        public string $name,
        protected int $dims = 384,
        protected bool $index = true,
        protected VectorSimilarity $similarity = VectorSimilarity::Cosine,
        protected ?int $m = 64,
        protected ?int $efConstruction = 300,
    ) {}

    public function toRaw(): array
    {
        $raw = [
            $this->name => [
                'type' => $this->type,
                'dimension' => $this->dims,
            ],
        ];

        if ($this->index) {
            $raw[$this->name]['method'] = [
                'name' => 'hnsw',
                'space_type' => $this->mapSimilarity($this->similarity),
                'engine' => 'lucene',  // Changed from 'nmslib' to 'lucene' for OpenSearch 3.0+
                'parameters' => [
                    'm' => $this->m,
                    'ef_construction' => $this->efConstruction,
                ],
            ];
        }

        return $raw;
    }

    protected function mapSimilarity(VectorSimilarity $similarity): string
    {
        return match ($similarity) {
            VectorSimilarity::Cosine => 'cosinesimil',
            VectorSimilarity::DotProduct => 'innerproduct',
            VectorSimilarity::Euclidean => 'l2',
            VectorSimilarity::MaxInnerProduct => 'innerproduct',
        };
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
        return 'hnsw';
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
        return null;
    }

    public function oversample(): ?int
    {
        return null;
    }

    public function createSuffix(): string
    {
        if (! $this->index) {
            return 'exact_dims'.$this->dims.'_'.$this->similarity->value.'_'.VectorStrategy::Concatenate->value;
        }

        return 'm'.$this->m.'_efc'.$this->efConstruction.'_dims'.$this->dims.'_'.$this->similarity->value.'_'.VectorStrategy::Concatenate->value;
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

    public function vectorQueries(array $vector, int $k, Boolean $filter): array
    {
        return [
            new OpenSearchKnn(
                field: '_embeddings.'.$this->fullPath,
                queryVector: $vector,
                k: $k,
                numCandidates: 0, // OpenSearch doesn't use numCandidates
                filter: $filter->toRaw(),
                boost: 1.0
            ),
        ];
    }
}
