<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

use Sigmie\Shared\Contracts\FromRaw;
use Sigmie\Shared\Contracts\ToRaw;
use Sigmie\Shared\Contracts\Name;

interface TokenFilter extends ToRaw, FromRaw, Name
{
    public function value(): array;
}
