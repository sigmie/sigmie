<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Enums\SearchEngineType;
use Sigmie\Mappings\Contracts\Type;
use Sigmie\Mappings\Types\BaseVector;
use Sigmie\Mappings\Types\NestedVector;

interface SearchEngine
{
    public function engine(): SearchEngineType;

    /**
     * Format a SigmieVector field using engine-specific structure
     */
    public function vectorField(BaseVector $field): Type;

    /**
     * Format a NestedVector field using engine-specific structure
     */
    public function nestedVectorField(NestedVector $field): Type;

    /**
     * Return engine-specific index settings for semantic/vector fields
     */
    public function indexSettings(): array;
}
