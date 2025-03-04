<?php

declare(strict_types=1);

namespace Sigmie\Languages\German\Filter;

use function Sigmie\Functions\name_configs;
use Sigmie\Index\Analysis\TokenFilter\TokenFilter;

/**
 * @see http://members.unine.ch/jacques.savoy/clef/morpho.pdf
 */
class MinimalStemmer extends TokenFilter
{
    public function __construct(string $name = 'german_stemmer_minimal')
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
            'language' => 'minimal_german',
        ];
    }
}
