<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;

class DenseVector extends BaseVector
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

        $this->type = 'dense_vector';
    }
}
