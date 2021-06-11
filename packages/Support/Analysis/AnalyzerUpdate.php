<?php

declare(strict_types=1);

namespace Sigmie\Support\Analysis;

use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Support\Analysis\Tokenizer\Builder as TokenizerBuilder;
use Sigmie\Support\Update\Update as UpdateBuilder;

class AnalyzerUpdate
{
    protected Analyzer $analyzer;

    public function __construct(
        protected UpdateBuilder $builder,
        protected string $name
    ) {
    }

    public function addStopwords(array $stopwords)
    {
        $this->analyzer->addFilters();
        return;
    }

    public function tokenizeOn(): TokenizerBuilder
    {
        return new TokenizerBuilder($this->builder);
    }
}
