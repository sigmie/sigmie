<?php

declare(strict_types=1);

namespace Sigmie\Support\Shared;

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
use Sigmie\Base\Contracts\Tokenizer as TokenizerInterface;
use Sigmie\Support\Contracts\TokenizerBuilder as TokenizerBuilderInterface;
use Sigmie\Support\Index\TokenizerBuilder;

use function Sigmie\Helpers\random_letters;

trait Tokenizer
{
    protected TokenizerInterface $tokenizer;

    public function setTokenizer(TokenizerInterface $tokenizer): static
    {
        $this->tokenizer = $tokenizer;

        return $this;
    }

    abstract public function tokenizeOn(): TokenizerBuilderInterface;
}
