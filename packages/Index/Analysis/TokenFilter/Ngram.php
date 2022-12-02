<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\TokenFilter;

use function Sigmie\Functions\name_configs;

class Ngram extends TokenFilter
{
    public function __construct(
        string $name,
        protected string|int $minGram = 1,
        protected string|int $maxGram = 2,
        protected bool $preserveOriginal = false,
    ) {
        parent::__construct(name: $name, settings:[
            'min_gram' => $this->minGram,
            'max_gram' => $this->maxGram,
            'preserve_original' => $this->preserveOriginal
        ]);
    }

    public function type(): string
    {
        return 'ngram';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $configs] = name_configs($raw);

        $instance = new static(
            $name,
            $configs['min_gram'],
            $configs['max_gram'],
            $configs['preserve_original'] ?? false
        );

        return $instance;
    }

    protected function getValues(): array
    {
        return $this->settings;
    }
}
