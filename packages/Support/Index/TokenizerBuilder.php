<?php

declare(strict_types=1);

namespace Sigmie\Support\Index;

use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\Analysis;
use Sigmie\Base\Index\Builder as IndexBuilder;
use Sigmie\Support\Analysis\Tokenizer\TokenizerBuilder as TokenizerTokenizerBuilder;
use Sigmie\Support\Contracts\TokenizerBuilder as TokenizerBuilderInterface;


class TokenizerBuilder implements TokenizerBuilderInterface
{
    use TokenizerTokenizerBuilder;

    public function __construct(protected IndexBuilder $indexBuilder)
    {
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

    protected function analysis(): Analysis
    {
        return $this->indexBuilder->analysis();
    }
}
