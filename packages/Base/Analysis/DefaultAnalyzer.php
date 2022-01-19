<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Contracts\Tokenizer;

class DefaultAnalyzer extends Analyzer
{
    public const name = 'default';

    public function __construct(
        ?Tokenizer $tokenizer = null,
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
