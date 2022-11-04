<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis;

use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\Tokenizer;

class DefaultAnalyzer extends Analyzer
{
    public const name = 'default';

    public function __construct(
        Tokenizer $tokenizer = new WordBoundaries(),
        array $filters = [],
        array $charFilters = [],
    ) {
        parent::__construct(
            self::name,
            $tokenizer,
            $filters,
            $charFilters
        );
    }
}
