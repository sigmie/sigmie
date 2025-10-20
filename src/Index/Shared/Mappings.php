<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Composer\InstalledVersions;
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

    protected array $customMeta = [];

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

    public function meta(array $meta): static
    {
        $this->customMeta = [...$this->customMeta, ...$meta];

        return $this;
    }

    protected function createMappings(DefaultAnalyzer $defaultAnalyzer): MappingsInterface
    {
        $defaultMeta = [
            "created_by" => "sigmie",
            "lib_version" => InstalledVersions::getVersion('sigmie/sigmie'),
            "language" => $this->language,
        ];

        return new IndexMappings(
            defaultAnalyzer: $defaultAnalyzer,
            properties: $this->properties,
            meta: [...$defaultMeta, ...$this->customMeta],
            driver: $this->elasticsearchConnection->driver(),
        );
    }
}
