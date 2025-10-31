<?php

declare(strict_types=1);

namespace Sigmie\Base\Drivers;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Enums\SearchEngineType;
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
        return new KnnVector(
            name: $field->name,
            dims: $field->dims(),
            index: $field->isIndexed(),
            similarity: $field->similarity(),
            m: $field->m(),
            efConstruction: $field->efConstruction(),
        );
    }

    public function nestedVectorField(NestedVector $field): Type
    {
        return new OpenSearchNestedVector(
            name: $field->name,
            dims: $field->dims,
            similarity: $field->similarity,
        );
    }

    public function indexSettings(): array
    {
        return ['index.knn' => true];
    }
}
