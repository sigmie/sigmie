<?php declare(strict_types=1);


namespace Sigmie\Base\Contracts;

interface Tokenizer
{
    public function name(): string;

    public function type(): string;
}
