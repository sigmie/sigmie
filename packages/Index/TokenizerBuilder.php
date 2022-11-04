<?php

declare(strict_types=1);

namespace Sigmie\Index;

use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Builder as IndexBuilder;
use Sigmie\Index\Analysis\Tokenizer\TokenizerBuilder as TokenizerTokenizerBuilder;
use Sigmie\Index\Contracts\TokenizerBuilder as TokenizerBuilderInterface;

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
