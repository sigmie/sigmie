<?php

declare(strict_types=1);


namespace Sigma\Listener;

class ValidateMappings
{
    public function __construct()
    {
    }

    public function handle()
    {
        dump('validated mapings');
    }
}
