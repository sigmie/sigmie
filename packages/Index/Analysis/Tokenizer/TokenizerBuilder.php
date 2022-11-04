<?php

declare(strict_types=1);

namespace Sigmie\Index\Analysis\Tokenizer;

use Exception;
use Sigmie\Index\Analysis\Tokenizers\Pattern;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\Contracts\Analysis;
use Sigmie\Index\Contracts\Tokenizer;

use function Sigmie\Helpers\random_letters;

trait TokenizerBuilder
{
    private Tokenizer $tokenizer;

    abstract public function analysis(): Analysis;

    protected function tokenizer(): Tokenizer
    {
        return $this->tokenizer;
    }

    protected function tokenizeOnWhiteSpaces(): void
    {
        $this->setTokenizer(new Whitespace());
    }

    protected function tokenizeOnPattern(
        string $pattern,
        null|string $flags = null,
        string|null $name = null
    ): void {
        $name = $name ?? $this->createTokenizerName('pattern_tokenizer');

        $this->setTokenizer(new Pattern($name, $pattern, $flags));
    }

    protected function tokenizeOnWordBoundaries(string|null $name = null): void
    {
        $name = $name ?? $this->createTokenizerName('standard');

        $this->setTokenizer(new WordBoundaries($name));
    }

    private function ensureTokenizerNameIsAvailable(string $name): void
    {
        if ($this->analysis()->hasTokenizer($name)) {
            throw new Exception('Tokenizer already exists.');
        }
    }

    private function setTokenizer(Tokenizer $tokenizer): void
    {
        $this->ensureTokenizerNameIsAvailable($tokenizer->name());

        $this->analysis()->addTokenizer($tokenizer);

        $this->tokenizer = $tokenizer;
    }

    private function createTokenizerName(string $name): string
    {
        $suffixed = $name . '_' . random_letters();

        while ($this->analysis()->hasTokenizer($suffixed)) {
            $suffixed = $name . '_' . random_letters();
        }

        return $suffixed;
    }
}
