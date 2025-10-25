<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorSimilarity;
use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Nested as TypesNested;
use Sigmie\Query\FunctionScore;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Query\Queries\Text\Nested;

/**
 * Utility helper for vector fields in nested structures
 * Not instantiated directly - use ElasticsearchNestedVector or OpenSearchNestedVector instead
 */
class NestedVector extends TypesNested
{
    public function __construct(
        string $name,
        public readonly int $dims,
        public readonly string $apiName,
        public readonly VectorStrategy $strategy = VectorStrategy::Concatenate,
        public readonly VectorSimilarity $similarity = VectorSimilarity::Cosine,
    ) {
        $props = new NewProperties();
        $props->type(
            new BaseVector(
                name: 'vector',
                dims: $dims,
                strategy: $strategy,
            )
        );

        parent::__construct($name, $props);
    }

    public function dims(): int {
        return $this->dims;
    }
}
