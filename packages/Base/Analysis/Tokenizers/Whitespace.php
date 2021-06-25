<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\Tokenizer;

class Whitespace implements Tokenizer
{
    public function name(): string
    {
        return 'whitespace';
    }
}
