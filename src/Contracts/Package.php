<?php

declare(strict_types=1);

namespace Sigmie\Contracts;

use Sigmie\Sigmie;

interface Package
{
    /**
     * Register macros, collection hooks, or any other extensions with Sigmie.
     * Called immediately when the application invokes extend() on a Sigmie instance.
     */
    public function register(Sigmie $sigmie): void;
}
