<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface TokenFilter extends Name, Raw, Type
{
    public function value(): array;
}
