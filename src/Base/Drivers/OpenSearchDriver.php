<?php

declare(strict_types=1);

namespace Sigmie\Base\Drivers;

use Sigmie\Base\Contracts\SearchEngineDriver;
use Sigmie\Enums\SearchEngine;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Mappings\Types\KnnVector;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\OpenSearchNestedVector;
use Sigmie\Mappings\Types\SigmieVector;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Query\Queries\OpenSearchKnn;

class OpenSearchDriver implements SearchEngineDriver
{
    public function engine(): SearchEngine
    {
        return SearchEngine::OpenSearch;
    }

    public function vectorField(SigmieVector $field): SigmieVector
    {
        return new KnnVector(
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
        return new OpenSearchNestedVector(
            name: $field->name,
            dims: $field->dims(),
            apiName: $field->apiName,
        );
    }

    public function indexSettings(bool $hasSemanticFields): array
    {
        // OpenSearch requires 'index.knn' setting enabled for vector fields
        return $hasSemanticFields ? ['index.knn' => true] : [];
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

    public function knnQuery(
        string $field,
        array|string $queryVector,
        int $k = 300,
        int $numCandidates = 1000,
        array $filter = [],
        float $boost = 1.0
    ): NearestNeighbors {
        return new OpenSearchKnn(
            field: $field,
            queryVector: $queryVector,
            k: $k,
            numCandidates: $numCandidates,
            filter: $filter,
            boost: $boost,
        );
    }
}
