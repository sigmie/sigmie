<?php

declare(strict_types=1);

namespace Sigmie\English\Filter;

use function Sigmie\Functions\name_configs;
use Sigmie\Index\Analysis\TokenFilter\TokenFilter;

/**
 * @see https://snowballstem.org/algorithms/porter/stemmer.html
 */
class Stemmer extends TokenFilter
{
    public function __construct(string $name = 'english_stemmer')
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
            'language' => 'english',
        ];
    }
}
