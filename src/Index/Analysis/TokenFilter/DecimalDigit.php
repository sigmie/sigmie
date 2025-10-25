<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class DecimalDigit extends TokenFilter
{
    public function __construct(
        string $name,
    ) {
        parent::__construct($name, []);
    }

    public function type(): string
    {
        return 'decimal_digit';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        return new static($name);
    }

    protected function getValues(): array
    {
        return [];
    }
}
