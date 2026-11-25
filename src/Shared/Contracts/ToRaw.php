<?php

declare(strict_types=1);

namespace Sigmie\Shared\Contracts;

use stdClass;

interface ToRaw
{
    public function toRaw(): array|stdClass;
}
