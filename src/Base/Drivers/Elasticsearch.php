<?php

declare(strict_types=1);

namespace Sigmie\Base\Drivers;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\ElasticsearchNestedVector;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Query\Queries\ElasticsearchKnn;
use Sigmie\Query\Queries\NearestNeighbors;

class Elasticsearch implements SearchEngine
{
    public function engine(): SearchEngineType
    {
        return SearchEngineType::Elasticsearch;
    }

    public function vectorField(BaseVector $field): BaseVector
    {
        return new DenseVector(
            name: $field->name,
            dims: $field->dims(),
            index: $field->isIndexed(),
            similarity: $field->similarity(),
            strategy: $field->strategy(),
            indexType: $field->indexType(),
            m: $field->m(),
            efConstruction: $field->efConstruction(),
            confidenceInterval: $field->confidenceInterval(),
            oversample: $field->oversample(),
            apiName: $field->apiName,
            boostedByField: $field->boostedByField(),
            autoNormalizeVector: $field->autoNormalizeVector(),
        );
    }

    public function nestedVectorField(NestedVector $field): NestedVector
    {
        return new ElasticsearchNestedVector(
            name: $field->name,
            dims: $field->dims(),
            apiName: $field->apiName,
        );
    }

    public function indexSettings(): array
    {
        return [];
    }

    public function knnQuery(
        string $field,
        array|string $queryVector,
        int $k = 300,
        int $numCandidates = 1000,
        array $filter = [],
        float $boost = 1.0
    ): NearestNeighbors {
        return new ElasticsearchKnn(
            field: $field,
            queryVector: $queryVector,
            k: $k,
            numCandidates: $numCandidates,
            filter: $filter,
            boost: $boost,
        );
    }
}
