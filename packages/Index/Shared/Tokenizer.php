<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Sigmie\Index\Analysis\Tokenizers\Pattern;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\Tokenizer as TokenizerInterface;
use Sigmie\Index\Contracts\TokenizerBuilder as TokenizerBuilderInterface;

use function Sigmie\Functions\random_letters;

trait Tokenizer
{
    protected TokenizerInterface $tokenizer;

    abstract public function analysis(): Analysis;

    public function tokenizer(TokenizerInterface $tokenizer): static
    {
        $this->tokenizer = $tokenizer;

        return $this;
    }

    public function tokenizeOnWhiteSpaces(): static
    {
        $this->tokenizer = new Whitespace();

        return $this;
    }

    private function createTokenizerName(string $name): string
    {
        $suffixed = $name.'_'.random_letters();

        while ($this->analysis()->hasTokenizer($suffixed)) {
            $suffixed = $name.'_'.random_letters();
        }

        return $suffixed;
    }

    public function tokenizeOnPattern(
        string $pattern,
        null|string $flags = null,
        string|null $name = null
    ): static {

        $name = $name ?? $this->createTokenizerName('pattern_tokenizer');

        $this->tokenizer(new Pattern($name, $pattern, $flags));

        return $this;
    }

    public function tokenizeOnWordBoundaries(string|null $name = null): static
    {
        $name = $name ?? $this->createTokenizerName('standard');

        $this->tokenizer(new WordBoundaries($name));

        return $this;
    }
}
