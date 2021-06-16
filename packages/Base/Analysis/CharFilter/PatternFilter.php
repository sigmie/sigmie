<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Priority;

use function Sigmie\Helpers\name_configs;

class PatternFilter extends ConfigurableCharFilter
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