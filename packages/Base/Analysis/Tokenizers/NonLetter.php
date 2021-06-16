<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\Tokenizer;

class NonLetter implements Tokenizer
{
    public function name(): string
    {
        return 'letter';
    }

    public function type(): string
    {
        return 'letter';
    }
}
