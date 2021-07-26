<?php

declare(strict_types=1);

namespace Sigmie\Http\Contracts;

interface Auth
{
    public function keys(): array;
}
