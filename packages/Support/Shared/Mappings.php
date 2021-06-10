<?php

declare(strict_types=1);

namespace Sigmie\Support\Shared;

use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Index\Mappings as IndexMappings;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Base\Index\DynamicMappings;

trait Mappings
{
    public function mappings(callable $callable)
    {
        $this->blueprintCallback = $callable;

        return $this;
    }

    protected function createMappings(DefaultAnalyzer $defaultAnalyzer): MappingsInterface
    {
        $mappings = new DynamicMappings($defaultAnalyzer);

        if ($this->dynamicMappings === false) {
            $blueprint = ($this->blueprintCallback)(new Blueprint);

            $properties = $blueprint();

            $mappings = new IndexMappings(
                defaultAnalyzer: $defaultAnalyzer,
                properties: $properties
            );
        }

        return $mappings;
    }
}
