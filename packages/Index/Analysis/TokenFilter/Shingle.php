<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

// Shingle generates only sequential terms for example.
// This is perfect for titles etc. In autocompletion
// all categories are equaly important that why we
// generate all possible permutations of them.
class Shingle extends TokenFilter
{
    public function __construct(
        string $name,
        protected string|int $minShingleSize = 2,
        protected string|int $maxShingleSize = 2,
    ) {
        parent::__construct(name: $name, settings: [
            'min_shingle_size' => $this->minShingleSize,
            'max_shingle_size' => $this->maxShingleSize,
        ]);
    }

    public function type(): string
    {
        return 'shingle';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $instance = new static(
            $name,
            $configs['min_shingle_size'],
            $configs['max_shingle_size']
        );

        return $instance;
    }

    protected function getValues(): array
    {
        return $this->settings;
    }
}
