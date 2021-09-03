<?php

declare(strict_types=1);

namespace Sigmie\Support\Update;

use Sigmie\Base\Contracts\Analysis;
use Sigmie\Support\Analysis\Tokenizer\TokenizerBuilder as TokenizerTokenizerBuilder;
use Sigmie\Support\Contracts\TokenizerBuilder as ContractsTokenizerBuilder;


class TokenizerBuilder implements ContractsTokenizerBuilder
{
    use TokenizerTokenizerBuilder;

    public function __construct(protected Update $updateBuilder)
    {
    }

    public function whiteSpaces(): Update
    {
        $this->tokenizeOnWhiteSpaces();

        $this->updateBuilder->setTokenizer($this->tokenizer());

        return $this->updateBuilder;
    }

    public function pattern(
        string $pattern,
        null|string $flags = null,
        string|null $name = null
    ): Update {
        $this->tokenizeOnPattern($pattern, $flags, $name);

        $this->updateBuilder->setTokenizer($this->tokenizer());

        return $this->updateBuilder;
    }

    public function wordBoundaries(string|null $name = null): Update
    {
        $this->tokenizeOnWordBoundaries($name);

        $this->updateBuilder->setTokenizer($this->tokenizer());

        return $this->updateBuilder;
    }

    protected function analysis(): Analysis
    {
        return $this->updateBuilder->analysis();
    }
}
