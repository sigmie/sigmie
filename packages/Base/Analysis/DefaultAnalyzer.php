<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contract\CharFilter;
use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Collection;

class DefaultAnalyzer extends Analyzer
{
    protected string $name = 'default';

    public function __construct(
        protected Tokenizer $tokenizer,
        protected array $filters,
        protected array $charFilterNames = [],
    ) {

        parent::__construct($this->name, $tokenizer, $filters, $charFilterNames);
    }
}
