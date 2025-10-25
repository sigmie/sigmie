<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Sigmie\Index\Analysis\Tokenizers\NonLetter;
use Sigmie\Index\Analysis\Tokenizers\Noop;
use Sigmie\Index\Analysis\Tokenizers\PathHierarchy;
use Sigmie\Index\Analysis\Tokenizers\Pattern;
use Sigmie\Index\Analysis\Tokenizers\SimplePattern;
use Sigmie\Index\Analysis\Tokenizers\SimplePatternSplit;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\Tokenizer as TokenizerInterface;

use function Sigmie\Functions\random_name;

trait Tokenizer
{
    protected TokenizerInterface $tokenizer;

    abstract public function analysis(): Analysis;

    public function tokenizer(TokenizerInterface $tokenizer): static
    {
        $this->tokenizer = $tokenizer;

        return $this;
    }

    public function dontTokenize(?string $name = null): static
    {
        $name ??= $this->createTokenizerName('whitespace');

        $this->tokenizer = new Noop($name);

        return $this;
    }

    public function tokenizeOnNonLetter(?string $name = null): static
    {
        $name ??= $this->createTokenizerName('non_letter');

        $this->tokenizer = new NonLetter($name);

        return $this;
    }

    public function tokenizePathHierarchy(string $delimiter = '/', ?string $name = null): static
    {
        $name ??= $this->createTokenizerName('path_hierarchy');

        $this->tokenizer = new PathHierarchy($name, $delimiter);

        return $this;
    }

    public function tokenizeOnWhitespaces(?string $name = null): static
    {
        $name ??= $this->createTokenizerName('whitespace');

        $this->tokenizer = new Whitespace($name);

        return $this;
    }

    private function createTokenizerName(string $name): string
    {
        return random_name($name);
    }

    public function tokenizeOnPattern(
        string $pattern,
        ?string $flags = null,
        ?string $name = null
    ): static {
        $name ??= $this->createTokenizerName('pattern_tokenizer');

        $this->tokenizer(new Pattern($name, $pattern, $flags));

        return $this;
    }

    public function tokenizeOnSimplePattern(
        string $pattern,
        ?string $name = null
    ): static {
        $name ??= $this->createTokenizerName('simple_pattern_split_tokenizer');

        $this->tokenizer(new SimplePatternSplit($name, $pattern));

        return $this;
    }

    public function tokenizeOnPatternMatch(
        string $pattern,
        ?string $name = null
    ): static {
        $name ??= $this->createTokenizerName('simple_pattern');

        $this->tokenizer(new SimplePattern($name, $pattern));

        return $this;
    }

    public function tokenizeOnWordBoundaries(?string $name = null): static
    {
        $name ??= $this->createTokenizerName('standard');

        $this->tokenizer(new WordBoundaries($name));

        return $this;
    }
}
