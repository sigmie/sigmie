<?php

declare(strict_types=1);

namespace Sigmie\Support\Analysis;

use Sigmie\Support\Analysis\Tokenizer\Builder as TokenizerBuilder;
use Sigmie\Support\Update\Update as UpdateBuilder;

class AnalyzerUpdate
{
    public function __construct(
        protected UpdateBuilder $builder,
        protected string $name
    ) {
    }

    public function addStopwords(array $stopwords)
    {
        return;
    }

    public function tokenizeOn(): TokenizerBuilder
    {
        return new TokenizerBuilder($this->builder);
    }
}
