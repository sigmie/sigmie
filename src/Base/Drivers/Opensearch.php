<?php

declare(strict_types=1);

namespace Sigmie\Base\Drivers;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\KnnVector;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\OpenSearchNestedVector;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Query\Queries\OpenSearchKnn;

class Opensearch implements SearchEngine
{
    public function engine(): SearchEngineType
    {
        return SearchEngineType::OpenSearch;
    }

    public function vectorField(BaseVector $field): Type
    {
        $vector = new KnnVector(
            name: $field->name,
            dims: $field->dims(),
            index: $field->isIndexed(),
            similarity: $field->similarity(),
            m: $field->m(),
            efConstruction: $field->efConstruction(),
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
        $nestedVector = new OpenSearchNestedVector(
            name: $field->name,
            dims: $field->dims(),
            apiName: $field->apiName,
        );

        $nestedVector->fullPath = $field->fullPath;

        return $nestedVector;
    }

    public function indexSettings(): array
    {
        return  ['index.knn' => true];
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
