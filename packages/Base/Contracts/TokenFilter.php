<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface TokenFilter extends Name, Priority
{
    public function type(): string;

    public function value(): array;
}
