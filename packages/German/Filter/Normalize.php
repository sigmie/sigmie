<?php

declare(strict_types=1);

namespace Sigmie\German\Filter;

use Sigmie\Index\Analysis\TokenFilter\TokenFilter;

use function Sigmie\Functions\name_configs;

class Normalize extends TokenFilter
{
    public function __construct(string $name = 'german_normalization')
    {
        parent::__construct($name);
    }

    public function type(): string
    {
        return 'german_normalization';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name);
    }

    protected function getValues(): array
    {
        return [];
    }
}
