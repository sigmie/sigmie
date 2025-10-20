<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Enums\VectorStrategy;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Types\Nested as TypesNested;

class OpenSearchNestedVector extends NestedVector
{
    public function __construct(
        string $name,
        int $dims = 384,
        ?string $apiName = null,
    ) {
        $props = new NewProperties();
        $props->type(
            new KnnVector(
                name: 'vector',
                dims: $dims,
                strategy: VectorStrategy::ScriptScore,
                apiName: $apiName,
            )
        );

        parent::__construct($name, $props, $dims, $apiName);
    }
}
