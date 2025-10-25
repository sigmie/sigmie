<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\Types\Type as AbstractType;

/**
 * Abstract vector type that is driver-agnostic.
 * Contains only properties required by DocumentProcessor and driver conversions.
 */
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
        public readonly ?string $apiName = null,
        public readonly ?string $boostedByField = null,
        public readonly bool $autoNormalizeVector = true,
        public readonly ?string $queryApiName = null,
    ) {}

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

    public function boostedByField(): ?string
    {
        return $this->boostedByField;
    }

    public function autoNormalizeVector(): bool
    {
        return $this->autoNormalizeVector;
    }
}
