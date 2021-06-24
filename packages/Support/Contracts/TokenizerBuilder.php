<?php

declare(strict_types=1);

namespace Sigmie\Support\Contracts;

interface TokenizerBuilder
{
    public function whiteSpaces();

    public function pattern(string $pattern, string|null $name = null);

    public function wordBoundaries(string|null $name = null);
}
