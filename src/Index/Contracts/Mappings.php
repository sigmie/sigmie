<?php

declare(strict_types=1);

namespace Sigmie\Index\Contracts;

use Sigmie\Base\Contracts\SearchEngine;
use Sigmie\Mappings\Properties;
use Sigmie\Shared\Contracts\ToRaw;

interface Mappings
{
    public static function create(array $raw, array $analyzers): static;

    public function analyzers(): array;

    public function properties(): Properties;

    public function toRaw(SearchEngine $driver): array;
}
