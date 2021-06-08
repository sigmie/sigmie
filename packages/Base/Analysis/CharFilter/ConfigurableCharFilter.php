<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Sigmie\Base\Contracts\CharFilter as CharFilterInterface;
use Sigmie\Base\Contracts\Configurable;

abstract class ConfigurableCharFilter implements CharFilterInterface, Configurable
{
    public function __construct(
        protected string $name
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }
}
