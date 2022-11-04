<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Sigmie\Index\Contracts\Tokenizer as TokenizerInterface;
use Sigmie\Index\Contracts\TokenizerBuilder as TokenizerBuilderInterface;

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
