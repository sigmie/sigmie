<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class SynonymGraph extends TokenFilter
{
    public function __construct(
        string $name,
        protected array $synonyms = [],
    ) {
        parent::__construct($name, [
            'synonyms' => $this->synonyms,
        ]);
    }

    public function type(): string
    {
        return 'synonym_graph';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $settings = [];

        foreach ($configs['synonyms'] as $value) {
            $settings[] = $value;
        }

        $instance = new static($name, $settings);

        return $instance;
    }

    public function toRaw(): array
    {
        return [
            $this->name() => [
                'type' => $this->type(),
                'synonyms' => $this->synonyms,
            ]
        ];
    }
}
