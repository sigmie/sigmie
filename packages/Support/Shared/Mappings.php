<?php

declare(strict_types=1);

namespace Sigmie\Support\Shared;

use Closure;
use Exception;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Base\Index\DynamicMappings;
use Sigmie\Base\Index\Mappings as IndexMappings;
use Sigmie\Support\Callables\Properties as BlueprintProxy;

trait Mappings
{
    protected bool $dynamicMappings = false;

    protected Closure $blueprintCallback;

    public function mapping(callable $callable): static
    {
        $this->blueprintCallback = $callable;

        return $this;
    }

    protected function createMappings(DefaultAnalyzer $defaultAnalyzer): MappingsInterface
    {
        $mappings = new DynamicMappings($defaultAnalyzer);

        if ($this->dynamicMappings === false && isset($this->blueprintCallback)) {

            $properties = (new BlueprintProxy)($this->blueprintCallback);

            $mappings = new IndexMappings(
                defaultAnalyzer: $defaultAnalyzer,
                properties: $properties
            );
        }

        return $mappings;
    }
}
