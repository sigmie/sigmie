<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\CharFilter;

use function Sigmie\Functions\name_configs;

class Generic extends CharFilter
{
    public function __construct(private readonly string $name, private readonly array $settings) {}

    public static function fromRaw(array $raw): static
    {
        return new static(...name_configs($raw));
    }

    public function name(): string
    {
        return $this->name;
    }

    public function toRaw(): array
    {
        return [$this->name => $this->settings];
    }
}
