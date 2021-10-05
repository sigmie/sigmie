<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Mappings\Properties;
use Sigmie\Support\Contracts\Collection;

interface Mappings extends Analyzers
{
    public function properties(): Properties;

    public function toRaw(): array;

    public static function fromRaw(array $raw, Collection $analyzers): static;
}
