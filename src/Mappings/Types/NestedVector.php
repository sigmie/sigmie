<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

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
}
