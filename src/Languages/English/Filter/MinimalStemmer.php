<?php

declare(strict_types=1);

namespace Sigmie\Languages\English\Filter;

use function Sigmie\Functions\name_configs;
use Sigmie\Index\Analysis\TokenFilter\TokenFilter;

/**
 * @see https://www.researchgate.net/publication/220433848_How_effective_is_suffixing
 */
class MinimalStemmer extends TokenFilter
{
    public function __construct(string $name = 'english_stemmer_minimal')
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
            'language' => 'minimal_english',
        ];
    }
}
