<?php

declare(strict_types=1);

namespace Sigmie\Base\Drivers;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\DenseVector;
use Sigmie\Mappings\Types\ElasticsearchNestedVector;
use Sigmie\Mappings\Types\NestedVector;

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
        $vector->apiName = $field->apiName ?? null;
        $vector->boostedByField = $field->boostedByField();
        $vector->autoNormalizeVector = $field->autoNormalizeVector();

        return $vector;
    }

    public function nestedVectorField(NestedVector $field): Type
    {
        $nestedVector = new ElasticsearchNestedVector(
            name: $field->name,
            dims: $field->dims,
            apiName: $field->apiName,
        );

        $nestedVector->fullPath = $field->fullPath;

        return $nestedVector;
    }

    public function indexSettings(): array
    {
        return [];
    }
}
