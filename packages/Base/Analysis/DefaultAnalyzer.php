<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Ramsey\Collection\CollectionInterface;
use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Collection;

class DefaultAnalyzer extends Analyzer
{
    const name = 'default';

    public function __construct(
        ?Tokenizer $tokenizer = null,
        array $filters = [],
        array $charFilters = [],
    ) {

        // 'standard' is the default Elasticsearch
        // tokenizer when no other is specified
        $tokenizer = $tokenizer ?: new WordBoundaries();

        parent::__construct(
            self::name,
            $tokenizer,
            $filters,
            $charFilters
        );
    }

    public static function fromRaw(Tokenizer $tokenizer, $analyzerFilters, $charFilters)
    {
        return new static($tokenizer, $analyzerFilters, $charFilters);
    }
}
