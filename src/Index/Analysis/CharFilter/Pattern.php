<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\CharFilter;

use function Sigmie\Functions\name_configs;

class Pattern extends ConfigurableCharFilter
{
    public function __construct(
        string $name,
        protected string $pattern,
        protected string $replacement,
        protected ?string $flags = null,
    ) {
        parent::__construct($name);
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        return new static(
            $name,
            $configs['pattern'],
            $configs['replacement'] ?? '',
            $configs['flags'] ?? null,
        );
    }

    public function settings(array $settings): void
    {
        $this->pattern = $settings['pattern'];
        $this->replacement = $settings['replacement'] ?? '';
        $this->flags = $settings['flags'] ?? null;
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => 'pattern_replace',
                'pattern' => $this->pattern,
                'replacement' => $this->replacement,
                ...($this->flags === null ? [] : ['flags' => $this->flags]),
            ],
        ];
    }
}
