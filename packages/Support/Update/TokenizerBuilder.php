<?php

declare(strict_types=1);

namespace Sigmie\Support\Update;

use Sigmie\Base\Analysis\TokenFilter\OneWaySynonyms;
use Sigmie\Base\Analysis\TokenFilter\Stemmer;
use Sigmie\Base\Analysis\TokenFilter\Stopwords;
use Sigmie\Base\Analysis\TokenFilter\Synonyms;
use Sigmie\Base\Analysis\TokenFilter\TwoWaySynonyms;
use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\Analysis;
use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Base\Contracts\Language;
use Sigmie\Base\Contracts\TokenFilter;
use Sigmie\Support\Collection as SupportCollection;
use Sigmie\Support\Contracts\Collection;
use Sigmie\Base\Contracts\Tokenizer as TokenizerInterface;
use Sigmie\Base\Index\Builder as IndexBuilder;
use Sigmie\Support\Analysis\Tokenizer\TokenizerBuilder as TokenizerTokenizerBuilder;
use Sigmie\Support\Contracts\TokenizerBuilder as ContractsTokenizerBuilder;

use function Sigmie\Helpers\random_letters;

class TokenizerBuilder implements ContractsTokenizerBuilder
{
    use TokenizerTokenizerBuilder;

    public function __construct(protected Update $updateBuilder)
    {
    }

    protected function analysis(): Analysis
    {
        return $this->updateBuilder->analysis();
    }

    public function whiteSpaces(): Update
    {
        $this->tokenizeOnWhiteSpaces();

        $this->updateBuilder->setTokenizer($this->tokenizer());

        return $this->updateBuilder;
    }

    public function pattern(string $pattern, string|null $name = null): Update
    {
        $this->tokenizeOnPattern($pattern, $name);

        $this->updateBuilder->setTokenizer($this->tokenizer());

        return $this->updateBuilder;
    }

    public function wordBoundaries(string|null $name = null): Update
    {
        $this->tokenizeOnWordBoundaries($name);

        $this->updateBuilder->setTokenizer($this->tokenizer());

        return $this->updateBuilder;
    }
}
