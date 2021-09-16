<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Support\Contracts\Collection;

interface LanguageBuilder
{
    public function alias(string $alias);
}
