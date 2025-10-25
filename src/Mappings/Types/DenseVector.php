<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\Type as AbstractType;
use Sigmie\Query\Queries\Compound\Boolean;
use Sigmie\Query\Queries\ElasticsearchKnn;

class DenseVector extends AbstractType implements Type
{
    public string $type = 'dense_vector';

    public ?string $textFieldName = null;

    public function __construct(
        string $name,
        protected int $dims = 384,
        protected bool $index = true,
        protected VectorSimilarity $similarity = VectorSimilarity::Cosine,
        protected string $indexType = 'hnsw',
        protected ?int $m = 64,
        protected ?int $efConstruction = 300,
        ?string $fullPath = '',
    ) {
        parent::__construct($name, fullPath: $fullPath);
    }

    public function toRaw(): array
    {
        $raw = [
            $this->name => [
                'type' => $this->type,
                'dims' => $this->dims,
                'index' => $this->index,
            ],
        ];

        if ($this->index) {
            $raw[$this->name]['similarity'] = $this->similarity->value;
            $raw[$this->name]['index_options'] = [
                'type' => $this->indexType,
                'm' => $this->m,
                'ef_construction' => $this->efConstruction,
            ];
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

    public function textFieldName(string $name): static
    {
        $this->textFieldName = $name;

        return $this;
    }

    public function embeddingsName(): string
    {
        return sprintf('%s.%s', $this->textFieldName, $this->name);
    }

    public function vectorQueries(array $vector, int $k, Boolean $filter): array
    {
        $numCandidates = max($k * 10, 1000);

        return [
            new ElasticsearchKnn(
                field: '_embeddings.'.$this->fullPath,
                queryVector: $vector,
                k: $k,
                numCandidates: $numCandidates,
                filter: $filter->toRaw(),
                boost: 1.0
            ),
        ];
    }
}
