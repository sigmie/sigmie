<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\CharFilter;

use function Sigmie\Functions\name_configs;

class Mapping extends ConfigurableCharFilter
{
    public function __construct(
        string $name,
        protected array $mappings = []
    ) {
        parent::__construct($name);
    }

    public function settings(array $settings): void
    {
        $this->mappings = $settings;
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);
        $mappings = [];

        foreach ($config['mappings'] as $mapping) {
            [$key, $value] = explode('=>', $mapping);

            $mappings[$key] = $value;
        }

        return new static($name, $mappings);
    }

    public function toRaw(): array
    {
        $mappings = [];

        foreach ($this->mappings as $key => $value) {
            $mappings[] = sprintf('%s => %s', $key, $value);
        }

        return [
            $this->name => [
                'type' => 'mapping',
                'mappings' => $mappings,
            ],
        ];
    }
}
