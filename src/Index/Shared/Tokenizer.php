<?php

declare(strict_types=1);

namespace Sigmie\Index\Shared;

use Exception;
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
    abstract public function analysis(): Analysis;

    public function tokenizer(TokenizerInterface $tokenizer): static
    {
        $existing = $this->analysis()->tokenizers()[$tokenizer->name()] ?? null;
        if ($existing !== null && $existing !== $this->pipeline()->tokenizer() && $existing->toRaw() !== $tokenizer->toRaw()) {
            throw new Exception(sprintf('Tokenizer name `%s` already exists.', $tokenizer->name()));
        }

        $this->pipeline()->setTokenizer($tokenizer);

        return $this;
    }

    public function dontTokenize(?string $name = null): static
    {
        $name ??= $this->createTokenizerName('whitespace');

        return $this->tokenizer(new Noop($name));
    }

    public function tokenizeOnNonLetter(?string $name = null): static
    {
        $name ??= $this->createTokenizerName('non_letter');

        return $this->tokenizer(new NonLetter($name));
    }

    public function tokenizePathHierarchy(string $delimiter = '/', ?string $name = null): static
    {
        $name ??= $this->createTokenizerName('path_hierarchy');

        return $this->tokenizer(new PathHierarchy($name, $delimiter));
    }

    public function tokenizeOnWhitespaces(?string $name = null): static
    {
        $name ??= $this->createTokenizerName('whitespace');

        return $this->tokenizer(new Whitespace($name));
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
