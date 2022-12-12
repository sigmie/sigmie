<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

use Sigmie\Shared\Contracts\FromRaw;
use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;

interface NormalizerFilter
{
    public function type(): string;
}
