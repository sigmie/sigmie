<?php

declare(strict_types=1);

namespace Sigmie\Base\Drivers;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Mappings\Contracts\Type;
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

    public function vectorField(BaseVector $field): Type
    {
        $vector = new DenseVector(
            name: $field->name,
            dims: $field->dims(),
            index: $field->isIndexed(),
            similarity: $field->similarity(),
            indexType: $field->indexType(),
            m: $field->m(),
            efConstruction: $field->efConstruction(),
            confidenceInterval: $field->confidenceInterval(),
            oversample: $field->oversample(),
        );

        $vector->fullPath = $field->fullPath;
        $vector->textFieldName = $field->textFieldName;
        $vector->apiName = $field->apiName ?? null;
        $vector->boostedByField = $field->boostedByField();
        $vector->autoNormalizeVector = $field->autoNormalizeVector();

        return $vector;
    }

    public function nestedVectorField(NestedVector $field): Type
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
