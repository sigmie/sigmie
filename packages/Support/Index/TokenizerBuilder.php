<?php

declare(strict_types=1);

namespace Sigmie\Support\Index;

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
use Sigmie\Support\Contracts\TokenizerBuilder as TokenizerBuilderInterface;

use function Sigmie\Helpers\random_letters;

class TokenizerBuilder implements TokenizerBuilderInterface
{
    use TokenizerTokenizerBuilder;

    public function __construct(protected IndexBuilder $indexBuilder)
    {
    }

    protected function analysis(): Analysis
    {
        return $this->indexBuilder->analysis();
    }

    public function whiteSpaces(): IndexBuilder
    {
        $this->tokenizeOnWhiteSpaces();

        $this->indexBuilder->setTokenizer($this->tokenizer());

        return $this->indexBuilder;
    }

    public function pattern(
        string $pattern,
        null|string $flags = null,
        string|null $name = null
    ): IndexBuilder {
        $this->tokenizeOnPattern(
            pattern: $pattern,
            flags: $flags,
            name: $name
        );

        $this->indexBuilder->setTokenizer($this->tokenizer());

        return $this->indexBuilder;
    }

    public function wordBoundaries(string|null $name = null): IndexBuilder
    {
        $this->tokenizeOnWordBoundaries($name);

        $this->indexBuilder->setTokenizer($this->tokenizer());

        return $this->indexBuilder;
    }
}
