<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface Configurable
{
    public function config(): array;

    public function name(): string;
}
