<?php


declare(strict_types=1);

namespace Sigmie\Support\Callables;

use Sigmie\Base\Contracts\Analysis;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Base\Mappings\Properties as MappingProperties;

/**
 * This class is just a proxy to allow the editor
 * to recognize the return type, without having 
 * the ugly var comment in code
 */
final class Properties
{
    public function __invoke(callable $callable): MappingProperties
    {
        return $callable(new Blueprint())();
    }
}
