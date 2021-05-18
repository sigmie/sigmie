<?php

declare(strict_types=1);

namespace Sigmie\Base\Analysis\Tokenizers;

use Sigmie\Base\Contracts\Tokenizer;

class Whitespaces implements Tokenizer
{

    public function type(): string
    {
        return 'whitespace';
    }

    public function name(): string
    {
        return 'some_name';
    }

    public function configurable(): bool
    {
        return false;
    }
}
