<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;

use function Sigmie\Helpers\name_configs;

class AsciiFolding extends TokenFilter
{
    public function __construct(
        protected string $name,
    ) {
        parent::__construct($name, []);
    }

    public function type(): string
    {
        return 'asciifolding';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $instance = new static($name);

        return $instance;
    }

    protected function getValues(): array
    {
        return [];
    }
}
