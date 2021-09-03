<?php

declare(strict_types=1);

namespace Sigmie\Support\Analysis;

use Sigmie\Base\Contracts\Analysis;
use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer as TokenizerInterface;
use Sigmie\Support\Analyzer\TokenizerBuilder as AnalyzerTokenizerBuilder;
use Sigmie\Support\Contracts\TokenizerBuilder;
use Sigmie\Support\Shared\CharFilters;
use Sigmie\Support\Shared\Filters;
use Sigmie\Support\Shared\Tokenizer;

class AnalyzerUpdate
{
    use CharFilters, Filters, Tokenizer;

    public function __construct(
        protected Analysis $analysis,
        protected Analyzer $analyzer
    ) {
        $this->filters = $analyzer->filters();
        $this->charFilters = $analyzer->charFilters();
        $this->tokenizer = $analyzer->tokenizer();
    }

    public function analysis(): Analysis
    {
        return $this->analysis;
    }

    public function tokenizeOn(): TokenizerBuilder
    {
        return new AnalyzerTokenizerBuilder($this);
    }

    public function filter(TokenFilter $tokenFilter): static
    {
        $this->addFilter($tokenFilter);
        $this->analyzer->addFilters([$tokenFilter->name() => $tokenFilter]);

        return $this;
    }

    public function charFilter(CharFilter $charFilter): static
    {
        $this->addCharFilter($charFilter);
        $this->analyzer->addCharFilters([$charFilter->name() => $charFilter]);

        return $this;
    }

    public function tokenizer(TokenizerInterface $tokenizer): static
    {
        $this->setTokenizer($tokenizer);

        $this->analyzer->updateTokenizer($tokenizer);

        return $this;
    }

    public function analyzer(): Analyzer
    {
        $this->analyzer->addCharFilters($this->charFilters);
        $this->analyzer->addFilters($this->filters);
        $this->analyzer->updateTokenizer($this->tokenizer);

        return $this->analyzer;
    }
}
