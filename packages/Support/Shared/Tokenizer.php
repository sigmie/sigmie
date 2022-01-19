<?php

declare(strict_types=1);

namespace Sigmie\Support\Shared;

use Sigmie\Base\Contracts\Tokenizer as TokenizerInterface;
use Sigmie\Support\Contracts\TokenizerBuilder as TokenizerBuilderInterface;

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
