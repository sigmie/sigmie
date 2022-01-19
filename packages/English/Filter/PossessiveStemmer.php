<?php

declare(strict_types=1);

namespace Sigmie\English\Filter;

use Sigmie\Base\Analysis\TokenFilter\TokenFilter;

use function Sigmie\Helpers\name_configs;

/**
 * @see https://lucene.apache.org/core/8_9_0/analyzers-common/org/apache/lucene/analysis/en/EnglishPossessiveFilter.html
 */
class PossessiveStemmer extends TokenFilter
{
    public function __construct(string $name = 'english_stemmer_possessive')
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
            'language' => 'possessive_english',
        ];
    }
}
