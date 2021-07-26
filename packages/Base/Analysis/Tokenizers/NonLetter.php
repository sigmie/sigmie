<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\Tokenizer;

class NonLetter implements Tokenizer
{
    public static function fromRaw(array $raw): static
    {
        return new static;
    }

    public function name(): string
    {
        return 'letter';
    }
}
