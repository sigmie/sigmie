<?php

declare(strict_types=1);

namespace Sigmie\Support\Shared;

use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Index\Mappings as IndexMappings;
use Sigmie\Base\Mappings\Blueprint;
use Sigmie\Base\Mappings\DynamicMappings;

trait Mappings
{
    protected bool $dynamicMappings = false;

    protected Blueprint $blueprint;

    public function mapping(callable $callable): static
    {
        $this->blueprint = new Blueprint();

        $callable($this->blueprint);

        return $this;
    }

    protected function createMappings(DefaultAnalyzer $defaultAnalyzer): MappingsInterface
    {
        $mappings = new DynamicMappings($defaultAnalyzer);

        if ($this->dynamicMappings === false && isset($this->blueprint)) {
            $properties = ($this->blueprint)();

            $mappings = new IndexMappings(
                defaultAnalyzer: $defaultAnalyzer,
                properties: $properties
            );
        }

        return $mappings;
    }
}
