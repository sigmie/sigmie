<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Mappings as IndexMappings;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;
use Sigmie\Shared\Properties as SharedProperties;

trait Mappings
{
    use SharedProperties;

    abstract public function analysis(): Analysis;

    public function mapping(callable $callable): static
    {
        $newProperties = new NewProperties($this->analysis());

        $callable($newProperties);

        $this->properties($newProperties);

        return $this;
    }

    protected function createMappings(DefaultAnalyzer $defaultAnalyzer): MappingsInterface
    {
        return new IndexMappings(
            defaultAnalyzer: $defaultAnalyzer,
            properties: $this->properties
        );
    }
}
