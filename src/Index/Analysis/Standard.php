<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis;

use Sigmie\Index\Analysis\TokenFilter\Lowercase;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;

class Standard extends Analyzer
{
    public const name = 'standard';

    public function __construct(
    ) {
        $tokenizer = new WordBoundaries;
        $filters = [
            new Lowercase('lowercase'),
        ];
        $charFilters = [];

        parent::__construct(
            self::name,
            $tokenizer,
            $filters,
            $charFilters
        );
    }
}
