<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\CharFilter;

use Sigmie\Shared\Name;

abstract class ConfigurableCharFilter extends CharFilter
{
    use Name;

    public function __construct(
        public readonly string $name,
    ) {}

    abstract public function settings(array $settings): void;
}
