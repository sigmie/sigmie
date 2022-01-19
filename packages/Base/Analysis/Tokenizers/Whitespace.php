<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\Tokenizer;

class Whitespace implements Tokenizer
{
    public static function fromRaw(array $raw): static
    {
        return new static();
    }

    public function name(): string
    {
        return 'whitespace';
    }
}
