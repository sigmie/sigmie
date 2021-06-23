<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis;

use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\Synonyms;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Support\Collection as SupportCollection;
use Sigmie\Support\Contracts\Collection;
use Sigmie\Support\Analysis\Tokenizer\Builder as TokenizerBuilder;

use function Sigmie\Helpers\random_letters;

trait TokenizeOn
{
    public function tokenizeOn()
    {
        return new TokenizerBuilder($this->getAnalyzer(), $this);
    }

    abstract private function getAnalyzer(): Analyzer;
}
