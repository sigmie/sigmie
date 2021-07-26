<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Sigmie\Base\Contracts\CharFilter as CharFilterInterface;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Shared\Name;

abstract class ConfigurableCharFilter extends CharFilter
{
    use Name;
    public function __construct(
        protected string $name
    ) {
    }

    abstract public function settings(array $settings): void;
}
