<?php

declare(strict_types=1);

namespace Sigmie\Base\Drivers;

use Exception;
use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Enums\VectorSimilarity;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\KnnVector;
use Sigmie\Mappings\Types\NestedVector;
use Sigmie\Mappings\Types\OpenSearchNestedVector;

class Opensearch implements SearchEngine
{
    public function engine(): SearchEngineType
    {
        return SearchEngineType::OpenSearch;
    }

    public function vectorField(BaseVector $field): Type
    {
        if ($field->indexType() !== 'hnsw') {
            throw new Exception(sprintf('OpenSearch does not support the %s vector index type', $field->indexType()));
        }

        $knnVector = new KnnVector(
            name: $field->name,
            dims: $field->dims(),
            index: $field->isIndexed(),
            similarity: $field->similarity(),
            m: $field->m(),
            efConstruction: $field->efConstruction(),
        );

        $knnVector->meta($field->getMeta());

        return $knnVector->copyPathFrom($field);
    }

    public function nestedVectorField(NestedVector $field): Type
    {
        $nestedVector = new OpenSearchNestedVector(
            name: $field->name,
            dims: $field->dims,
            similarity: $field->similarity,
            autoNormalizeVector: $field->autoNormalizeVector && $field->boostedByField === null,
        );

        return $nestedVector->copyPathFrom($field);
    }

    public function indexSettings(): array
    {
        return ['index.knn' => true];
    }
}
