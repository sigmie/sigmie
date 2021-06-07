<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Contracts\Tokenizer;

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

        //TODO fix empty string
        parent::__construct('', $tokenizer, [], []);

        $this->name = "default";
    }

    public function raw(): array
    {
        $this->filters = $this->defaultFilters();

        return parent::raw();
    }

    public static function fromRaw(...$args)
    {
        return new static('foo', new Whitespaces);
    }

    protected function getPrefix(): string
    {
        return $this->prefix;
    }
}
