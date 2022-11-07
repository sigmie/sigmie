<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class Unique extends TokenFilter
{
    public function __construct(
        string $name,
        bool $onlyOnSamePosition = false
    ) {
        parent::__construct($name, ['only_on_same_position' => $onlyOnSamePosition]);
    }

    public function type(): string
    {
        return 'unique';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $onlyOnSamePosition = $configs['only_on_same_position'] ?? false;

        $instance = new static($name, $onlyOnSamePosition);

        return $instance;
    }

    protected function getValues(): array
    {
        return $this->settings;
    }
}
