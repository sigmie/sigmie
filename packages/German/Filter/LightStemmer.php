<?php

declare(strict_types=1);

namespace Sigmie\German\Filter;

use Sigmie\Index\Analysis\TokenFilter\TokenFilter;

use function Sigmie\Functions\name_configs;

/**
 * @see https://dl.acm.org/doi/10.1145/1141277.1141523
 */
class LightStemmer extends TokenFilter
{
    public function __construct(string $name = 'german_stemmer_light')
    {
        parent::__construct($name);
    }

    public function type(): string
    {
        return 'stemmer';
    }

    public static function fromRaw(array $raw): static
    {
        [$name, $config] = name_configs($raw);

        return new static($name);
    }

    protected function getValues(): array
    {
        return [
            'language' => 'light_german',
        ];
    }
}
