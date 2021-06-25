<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

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

    public static function fromRaw(array $raw)
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

    public function config(): array
    {
        return [
            'type' => 'pattern_replace',
            'pattern' => $this->pattern,
            'replacement' => $this->replacement,
            'class' => static::class
        ];
    }

    public function name(): string
    {
        return $this->name;
    }
}
