<?php

declare(strict_types=1);

namespace Sigmie\Support\Shared;

use Closure;
use Exception;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Contracts\Mappings as MappingsInterface;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Base\Index\DynamicMappings;
use Sigmie\Base\Index\Mappings as IndexMappings;

trait Mappings
{
    protected bool $dynamicMappings = false;

    protected Closure $blueprintCallback;

    public function mappings(callable $callable)
    {
        $this->blueprintCallback = $callable;

        return $this;
    }

    protected function createMappings(DefaultAnalyzer $defaultAnalyzer): MappingsInterface
    {
        $mappings = new DynamicMappings($defaultAnalyzer);

        if ($this->dynamicMappings === false && isset($this->blueprintCallback)) {
            $blueprint = ($this->blueprintCallback)(new Blueprint);

            if (is_null($blueprint)) {
                throw new Exception('Did you forget to return the blueprint ?');
            }

            $properties = $blueprint();

            $mappings = new IndexMappings(
                defaultAnalyzer: $defaultAnalyzer,
                properties: $properties
            );
        }

        return $mappings;
    }
}
