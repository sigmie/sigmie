<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Enums\SearchEngineType;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Query\Queries\NearestNeighbors;

interface SearchEngine
{
    public function engine(): SearchEngineType;

    /**
     * Format a SigmieVector field using engine-specific structure
     */
    public function vectorField(BaseVector $field): BaseVector;

    /**
     * Format a NestedVector field using engine-specific structure
     */
    public function nestedVectorField(NestedVector $field): NestedVector;

    /**
     * Return engine-specific index settings for semantic/vector fields
     */
    public function indexSettings(): array;

    /**
     * Create an engine-specific KNN query
     */
    public function knnQuery(
        string $field,
        array|string $queryVector,
        int $k = 300,
        int $numCandidates = 1000,
        array $filter = [],
        float $boost = 1.0
    ): NearestNeighbors;
}
