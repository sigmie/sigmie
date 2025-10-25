<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class Truncate extends TokenFilter
{
    public function __construct(
        string $name,
        int $length = 10
    ) {
        parent::__construct(
            name: $name,
            settings: ['length' => $length]
        );
    }

    public function type(): string
    {
        return 'truncate';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $length = $configs['length'] ?? 10;

        return new static($name, $length);
    }

    protected function getValues(): array
    {
        return $this->settings;
    }
}
