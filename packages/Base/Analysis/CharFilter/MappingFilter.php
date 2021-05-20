<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\CharFilter;

use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\Configurable;

class MappingFilter implements CharFilter, Configurable
{
    protected string $name = 'sigmie_mapping_char_filter';

    public function __construct(protected array $mappings = [])
    {
    }

    public function config(): array
    {
        $mappings = [];

        foreach ($this->mappings as $key => $value) {
            $mappings[] = "{$key} => {$value}";
        }

        return [
            'type' => 'mapping',
            'mappings' => $mappings
        ];
    }

    public function name(): string
    {
        return $this->name;
    }
}
