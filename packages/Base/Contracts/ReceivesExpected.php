<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface ReceivesExpected
{
    public function expected(?string $class = null): void;
}
