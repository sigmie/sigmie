<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface TokenFilter extends Name, Priority, Raw, Type
{
    public function value(): array;
}
