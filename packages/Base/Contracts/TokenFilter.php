<?php

namespace Sigmie\Base\Contracts;

interface TokenFilter
{
    public function name(): string;

    public function type(): string;

    public function value(): array;
}
