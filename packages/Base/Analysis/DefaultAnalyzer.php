<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Contracts\CharFilter;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Base\Contracts\Tokenizer;
use Sigmie\Support\Collection;

class DefaultAnalyzer extends Analyzer
{
    use DefaultFilters;

    public function __construct(
        protected string $prefix,
        ?Tokenizer $tokenizer = null,
        ?Stopwords $stopwords = null,
        ?TwoWaySynonyms $twoWaySynonyms = null,
        ?OneWaySynonyms $oneWaySynonyms = null,
        ?Stemmer $stemming = null,
    ) {
        $tokenizer ?: new Whitespaces;

        $this->stemming = $stemming;
        $this->stopwords = $stopwords;
        $this->twoWaySynonyms = $twoWaySynonyms;
        $this->oneWaySynonyms = $oneWaySynonyms;

        //TODO fix empty string
        parent::__construct(
            '',
            $tokenizer,
            [$stopwords, $twoWaySynonyms, $oneWaySynonyms, $stemming],
            []
        );

        $this->name = "default";
    }

    public function raw(): array
    {
        $this->filters = $this->defaultFilters();

        return parent::raw();
    }

    public function filters(): array
    {
        return $this->defaultFilters();
    }

    public static function fromRaw(string $name, Tokenizer $tokenizer, $analyzerFilters, $charFilters)
    {
        $filters = new Collection($analyzerFilters);

        $stopwords = $filters->filter(fn (TokenFilter $charFilter) => $charFilter instanceof Stopwords)->first();
        $twoWay = $filters->filter(fn (TokenFilter $charFilter) => $charFilter instanceof TwoWaySynonyms)->first();
        $oneWay = $filters->filter(fn (TokenFilter $charFilter) => $charFilter instanceof OneWaySynonyms)->first();
        $stemming = $filters->filter(fn (TokenFilter $charFilter) => $charFilter instanceof Stemmer)->first();

        return new static($name, $tokenizer, $stopwords, $twoWay, $oneWay, $stemming);
    }

    protected function getPrefix(): string
    {
        return $this->prefix;
    }
}
