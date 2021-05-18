<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\Configurable;
use Sigmie\Base\Contracts\Tokenizer;

class WordBoundaries implements Tokenizer, Configurable
{
    public function __construct(protected int $maxTokenLength = 255)
    {
    }

    public function name(): string
    {
        return 'my_tokenizer';
    }

    final public function type(): string
    {
        return 'standard';
    }

    public function config(): array
    {
        return [
            $this->name() => [
                "type" => $this->type(),
                "max_token_length" => $this->maxTokenLength
            ]
        ];
    }
}
