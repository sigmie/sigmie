<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Sigmie\Base\Shared\Priority;

use function Sigmie\Helpers\name_configs;

class Mapping extends ConfigurableCharFilter
{
    use Priority;

    public function __construct(
        protected string $name,
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
            $mappings[] = "{$key} => {$value}";
        }

        return [
            'type' => 'mapping',
            'mappings' => $mappings,
            'class' => static::class //TODO inerhit also from token filter
        ];
    }
}
