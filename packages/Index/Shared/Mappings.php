<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Mappings as IndexMappings;
use Sigmie\Mappings\Blueprint;
use Sigmie\Mappings\Properties;

trait Mappings
{
    protected Blueprint $blueprint;

    public function mapping(callable $callable): static
    {
        $this->blueprint = new Blueprint();

        $callable($this->blueprint);

        return $this;
    }

    protected function createMappings(DefaultAnalyzer $defaultAnalyzer): MappingsInterface
    {
        $properties = ($this->blueprint ?? false) ? ($this->blueprint)() : new Properties;

        return new IndexMappings(
            defaultAnalyzer: $defaultAnalyzer,
            properties: $properties
        );
    }
}
