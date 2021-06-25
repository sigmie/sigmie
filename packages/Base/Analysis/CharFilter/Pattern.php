<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Sigmie\Base\Shared\Name;
use Sigmie\Base\Shared\Priority;

use function Sigmie\Helpers\name_configs;

class Pattern extends ConfigurableCharFilter
{
    use Priority;

    public function __construct(
        protected string $name,
        protected string $pattern,
        protected string $replacement
    ) {
        parent::__construct($name);
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        return new static(
            $name,
            $configs['pattern'],
            $configs['replacement']
        );
    }

    public function settings(array $settings): void
    {
        $this->pattern = $settings['pattern'];
        $this->replacement = $settings['replacement'];
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => 'pattern_replace',
                'pattern' => $this->pattern,
                'replacement' => $this->replacement
            ]
        ];
    }
}
