<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;

class KnnVector extends SigmieVector
{
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
        parent::__construct(
            name: $name,
            dims: $dims,
            index: $index,
            similarity: $similarity,
            strategy: $strategy,
            indexType: $indexType,
            m: $m,
            efConstruction: $efConstruction,
            confidenceInterval: $confidenceInterval,
            oversample: $oversample,
            apiName: $apiName,
            boostedByField: $boostedByField,
            autoNormalizeVector: $autoNormalizeVector,
        );

        $this->type = 'knn_vector';
    }

    public function toRaw(): array
    {
        $raw = [
            $this->name => [
                'type' => $this->type,
                'dimension' => $this->dims,
            ]
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
}
