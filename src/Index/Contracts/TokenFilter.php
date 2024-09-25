<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

use Sigmie\Shared\Contracts\FromRaw;
use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;

interface TokenFilter extends FromRaw, Name, ToRaw
{
    public function value(): array;

    public function type(): string;
}
