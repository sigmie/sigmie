<?php

declare(strict_types=1);

namespace Sigmie\Support\Analyzer;

use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\Contracts\Analysis;
use Sigmie\Support\Analysis\AnalyzerUpdate;
use Sigmie\Support\Analysis\Tokenizer\TokenizerBuilder as TokenizerTokenizerBuilder;
use Sigmie\Support\Contracts\TokenizerBuilder as TokenizerBuilderInterface;


class TokenizerBuilder implements TokenizerBuilderInterface
{
    use TokenizerTokenizerBuilder;

    public function __construct(protected AnalyzerUpdate $analyzerUpdate)
    {
    }

    public function whiteSpaces(): AnalyzerUpdate
    {
        $this->tokenizeOnWhiteSpaces();

        $this->analyzerUpdate->setTokenizer($this->tokenizer());

        return $this->analyzerUpdate;
    }

    public function pattern(string $pattern, string|null $name = null): AnalyzerUpdate
    {
        $this->tokenizeOnPattern($pattern, $name);

        $this->analyzerUpdate->setTokenizer($this->tokenizer());

        return $this->analyzerUpdate;
    }

    public function wordBoundaries(string|null $name = null): AnalyzerUpdate
    {
        $this->tokenizeOnWordBoundaries($name);

        $this->analyzerUpdate->setTokenizer($this->tokenizer());

        return $this->analyzerUpdate;
    }

    protected function analysis(): Analysis
    {
        return $this->analyzerUpdate->analysis();
    }
}
