<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use function Sigmie\Functions\random_letters;

use Sigmie\Index\Analysis\Tokenizers\Noop;
use Sigmie\Index\Analysis\Tokenizers\PathHierarchy;
use Sigmie\Index\Analysis\Tokenizers\Pattern;
use Sigmie\Index\Analysis\Tokenizers\SimplePattern;
use Sigmie\Index\Analysis\Tokenizers\SimplePatternSplit;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\Tokenizer as TokenizerInterface;

trait Tokenizer
{
    protected TokenizerInterface $tokenizer;

    abstract public function analysis(): Analysis;

    public function tokenizer(TokenizerInterface $tokenizer): static
    {
        $this->tokenizer = $tokenizer;

        return $this;
    }

    public function dontTokenize(string|null $name = null): static
    {
        $name = $name ?? $this->createTokenizerName('whitespace');

        $this->tokenizer = new Noop($name);

        return $this;
    }

    public function tokenizePathHierarchy(string $delimiter = '/', string|null $name = null): static
    {
        $name = $name ?? $this->createTokenizerName('path_hierarchy');

        $this->tokenizer = new PathHierarchy($name, $delimiter);

        return $this;
    }

    public function tokenizeOnWhiteSpaces(string|null $name = null): static
    {
        $name = $name ?? $this->createTokenizerName('whitespace');

        $this->tokenizer = new Whitespace($name);

        return $this;
    }

    private function createTokenizerName(string $name): string
    {
        $suffixed = $name . '_' . random_letters();

        while ($this->analysis()->hasTokenizer($suffixed)) {
            $suffixed = $name . '_' . random_letters();
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

    public function tokenizeOnSimplePattern(
        string $pattern,
        string|null $name = null
    ): static {
        $name = $name ?? $this->createTokenizerName('simple_pattern_split_tokenizer');

        $this->tokenizer(new SimplePatternSplit($name, $pattern));

        return $this;
    }

    public function tokenizeOnPatternMatch(
        string $pattern,
        string|null $name = null
    ): static {
        $name = $name ?? $this->createTokenizerName('simple_pattern');

        $this->tokenizer(new SimplePattern($name, $pattern));

        return $this;
    }

    public function tokenizeOnWordBoundaries(string|null $name = null): static
    {
        $name = $name ?? $this->createTokenizerName('standard');

        $this->tokenizer(new WordBoundaries($name));

        return $this;
    }
}
