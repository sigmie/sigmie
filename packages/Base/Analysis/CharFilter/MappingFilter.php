<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Sigmie\Base\Priority;

class MappingFilter extends ConfigurableCharFilter
{
    use Priority;

    public function __construct(
        protected string $name,
        protected array $mappings = []
    ) {
        parent::__construct($name);
    }

    public function config(): array
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
