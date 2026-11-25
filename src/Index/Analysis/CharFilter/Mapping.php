<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\CharFilter;

use function Sigmie\Functions\analysis_list;
use function Sigmie\Functions\name_configs;

class Mapping extends ConfigurableCharFilter
{
    private array $rules = [];

    public function __construct(string $name, array $mappings = [], protected ?string $mappingsPath = null)
    {
        parent::__construct($name);
        if ($this->mappingsPath === null) {
            $this->settings($mappings);
        }
    }

    public function settings(array $settings): void
    {
        $this->mappingsPath = null;
        $this->rules = [];

        foreach ($settings as $key => $value) {
            $this->rules[] = $this->literal((string) $key).' => '.$this->literal((string) $value);
        }
    }

    private function literal(string $text): string
    {
        // Elasticsearch trims the rule sides, splits on => and interprets backslash escapes.
        return preg_replace_callback('/\A | \z|[\x00-\x1f\x{85}\x{2028}\x{2029}]|=(?=>)/u',
            static fn (array $match): string => sprintf('\\u%04x', mb_ord($match[0])),
            str_replace('\\', '\\\\', $text),
        );
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);
        if (isset($config['mappings_path'])) {
            return new static($name, mappingsPath: $config['mappings_path']);
        }

        $filter = new static($name);
        $filter->rules = analysis_list($config['mappings']);

        return $filter;
    }

    public function toRaw(): array
    {
        return [
            $this->name => [
                'type' => 'mapping',
                ...($this->mappingsPath === null ? ['mappings' => $this->rules] : ['mappings_path' => $this->mappingsPath]),
            ],
        ];
    }
}
