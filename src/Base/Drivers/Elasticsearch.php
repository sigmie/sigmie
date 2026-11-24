<?php

declare(strict_types=1);

namespace Sigmie\Base\Drivers;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Enums\SearchEngineType;
use Sigmie\Enums\VectorSimilarity;
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
        $similarity = $field->similarity();
        if (
            $similarity === VectorSimilarity::DotProduct
            && (! $field->autoNormalizeVector() || ($field->isIndexed() && $field->boostedByField() !== null))
        ) {
            $similarity = VectorSimilarity::MaxInnerProduct;
        }

        $denseVector = new DenseVector(
            name: $field->name,
            dims: $field->dims(),
            index: $field->isIndexed(),
            similarity: $similarity,
            indexType: $field->indexType(),
            m: $field->m(),
            efConstruction: $field->efConstruction(),
        );

        $denseVector->meta($field->getMeta());

        return $denseVector->copyPathFrom($field);
    }

    public function nestedVectorField(NestedVector $field): Type
    {
        $nestedVector = new ElasticsearchNestedVector(
            name: $field->name,
            dims: $field->dims,
            similarity: $field->similarity,
            autoNormalizeVector: $field->autoNormalizeVector && $field->boostedByField === null,
        );

        return $nestedVector->copyPathFrom($field);
    }

    public function indexSettings(): array
    {
        return [];
    }
}
