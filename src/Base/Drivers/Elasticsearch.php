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
        $denseVector = new DenseVector(
            name: $field->name,
            dims: $field->dims(),
            index: $field->isIndexed(),
            similarity: $field->similarity(),
            indexType: $field->indexType(),
            m: $field->m(),
            efConstruction: $field->efConstruction(),
        );

        if ($field->getParent() !== null) {
            $denseVector->setParent($field->getParent());
        }

        return $denseVector;
    }

    public function nestedVectorField(NestedVector $field): Type
    {
        $nestedVector = new ElasticsearchNestedVector(
            name: $field->name,
            dims: $field->dims,
            similarity: $field->similarity,
        );

        if ($field->getParent() !== null) {
            $nestedVector->setParent($field->getParent());
        }

        return $nestedVector;
    }

    public function indexSettings(): array
    {
        return [];
    }
}
