<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\Tokenizer;

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
}
