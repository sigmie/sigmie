<?php

declare(strict_types=1);

namespace Sigmie\Contracts;

interface Package
{
    /**
     * Register macros, collection hooks, or any other extensions with Sigmie.
     * Called immediately by Sigmie::extend().
     */
    public function register(): void;
}
