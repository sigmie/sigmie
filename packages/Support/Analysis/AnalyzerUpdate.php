<?php

declare(strict_types=1);

namespace Sigmie\Support\Analysis;

use Sigmie\Base\Analysis\CharFilter\HTMLFilter;
use Sigmie\Base\Analysis\CharFilter\MappingFilter;
use Sigmie\Base\Analysis\CharFilter\PatternFilter;
use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Analysis\Tokenizer\TokenizerBuilder as TokenizerBuilder;

class AnalyzerUpdate
{
    protected array $charFilter = [];
    public function __construct(
        protected Analyzer $analyzer,
        protected string $name
    ) {
    }

    public function addFilter(TokenFilter $tokenFilter)
    {
        $this->analyzer->addFilters([$tokenFilter]);
    }

    public function addCharFilter(CharFilter $charFilter)
    {
        $this->analyzer->addCharFilters([$charFilter]);
    }

    public function removeCharFilter(CharFilter|string $charFilter)
    {
        if ($charFilter instanceof CharFilter) {
            $this->analyzer->removeCharFilter($charFilter->name());
            return;
        }

        $this->analyzer->removeCharFilter($charFilter);
    }

    public function removeFilter(TokenFilter|string $tokenFilter)
    {
        if ($tokenFilter instanceof CharFilter) {
            $this->analyzer->removeFilter($tokenFilter->name());
            return;
        }

        $this->analyzer->removeFilter($tokenFilter);
    }

    public function setTokenizer(Tokenizer $tokenizer)
    {
        $this->analyzer->setTokenizer($tokenizer);
    }

    public function tokenizeOn(): TokenizerBuilder
    {
        return new TokenizerBuilder($this->analyzer);
    }

    public function patternReplace(
        string $pattern,
        string $replace,
        string|null $name = null,
    ) {
        $name = $name ?: $this->analyzer->name() . '_pattern_replace_filter';

        $this->analyzer->addCharFilters([new PatternFilter($name, $pattern, $replace)]);
    }

    public function stripHTML()
    {
        $this->analyzer->addCharFilters([new HTMLFilter]);
    }

    public function mapChars(
        array $mappings,
        string|null $name = null,
    ) {
        $name = $name ?: $this->analyzer->name() . '_mappings_filter';

        $this->analyzer->addCharFilters([new MappingFilter($name, $mappings)]);
    }

    public function analyzer(): Analyzer
    {
        return $this->analyzer;
    }
}
