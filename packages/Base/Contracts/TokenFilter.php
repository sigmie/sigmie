<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface TokenFilter extends Priority, Name
{
    public function type(): string;

    public function value(): array;
}
