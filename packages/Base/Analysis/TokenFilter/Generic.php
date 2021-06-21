<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\TokenFilter;


use function Sigmie\Helpers\name_configs;

class Generic extends TokenFilter
{
    public function type(): string
    {
        return $this->settings['type'];
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        return new static($name, $configs);
    }

    protected function getValues(): array
    {
        return $this->settings;
    }
}
