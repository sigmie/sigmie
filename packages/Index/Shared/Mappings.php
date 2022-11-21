<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Mappings as IndexMappings;
use Sigmie\Mappings\Blueprint;
use Sigmie\Mappings\Properties;

trait Mappings
{
    protected Blueprint $blueprint;

    public function blueprint(Blueprint $blueprint)
    {
        $this->blueprint = $blueprint;

        return $this;
    }

    abstract public function analysis(): Analysis;

    public function mapping(callable $callable): static
    {
        $this->blueprint = new Blueprint($this->analysis());

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
