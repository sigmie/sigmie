<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\CharFilter;

use Sigmie\Index\Contracts\CharFilter;

use function Sigmie\Functions\analysis_list;
use function Sigmie\Functions\name_configs;

class HTMLStrip implements CharFilter
{
    public function __construct(
        public string $name = 'html_strip',
        protected ?array $escapedTags = null,
    ) {}

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name, isset($config['escaped_tags']) ? analysis_list($config['escaped_tags']) : null);
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => 'html_strip',
                ...($this->escapedTags === null ? [] : ['escaped_tags' => array_values($this->escapedTags)]),
            ],
        ];
    }

    public function name(): string
    {
        return $this->name;
    }
}
