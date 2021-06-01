<?php

namespace Sigmie\Base\Contracts;

interface ConfigurableTokenizer extends Configurable, Tokenizer
{
    public function name(): string;
}
