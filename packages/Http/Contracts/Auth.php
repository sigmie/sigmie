<?php

declare(strict_types=1);

namespace Sigmie\Http\Contracts;

interface Auth
{
    /**
     * Guzzle config key
     */
    public function key(): string;

    /**
     * Guzzle config value
     *
     * @return mixed
     */
    public function value();
}
