<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Contracts;

use Sigmie\Shared\Contracts\Name;
use Sigmie\Shared\Contracts\ToRaw;

interface Type extends Name, ToRaw
{
    public function type(): string;

    public function name(): string;

    public function meta(array $meta): void;
}
