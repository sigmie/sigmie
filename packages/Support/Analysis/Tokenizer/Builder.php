<?php

declare(strict_types=1);

namespace Sigmie\Support\Analysis\Tokenizer;

use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\Analyzer;
use Sigmie\Support\Update\Update as UpdateBuilder;

class Builder
{
    public function __construct(
        protected Analyzer $analyzer,
        protected UpdateBuilder $builder
    ) {
    }

    public function whiteSpaces(): void
    {
        $this->analyzer->updateTokenizer(new Whitespaces);
    }

    public function pattern(string $pattern, string|null $name = null): void
    {
        $name = $name ?: $this->analyzer->name() . '_analyzer_pattern_tokenizer';

        $this->analyzer->updateTokenizer(new Pattern($name, $pattern));
    }

    public function wordBoundaries(): void
    {
        $this->analyzer->updateTokenizer(new WordBoundaries());
    }
}
