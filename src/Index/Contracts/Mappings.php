<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

use Sigmie\Mappings\Properties;
use Sigmie\Shared\Contracts\ToRaw;

interface Mappings extends ToRaw
{
    public static function create(array $raw, array $analyzers): static;

    public function analyzers(): array;

    public function properties(): Properties;
}
