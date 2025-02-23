<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Sigmie\Index\Analysis\Analysis as AnalysisAnalysis;
use Sigmie\Index\Analysis\DefaultAnalyzer;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\Mappings as MappingsInterface;
use Sigmie\Index\Mappings as IndexMappings;
use Sigmie\Mappings\NewProperties;
use Sigmie\Mappings\Properties;

trait Mappings
{
    protected Analysis $analysis;

    protected Properties $properties;

    public function analysis(): Analysis
    {
        return $this->analysis ?? new AnalysisAnalysis();
    }

    public function mapping(callable $callable): static
    {
        $newProperties = new NewProperties();

        $callable($newProperties);

        $this->properties($newProperties);

        return $this;
    }

    public function properties(NewProperties $props): static
    {
        $this->properties = $props->get(analysis: $this->analysis());

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
