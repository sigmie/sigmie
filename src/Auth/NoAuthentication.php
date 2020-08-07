<?php

declare(strict_types=1);

namespace Sigmie\Auth;

use Sigmie\Contracts\Authorizer;

class NoAuthentication implements Authorizer
{
    public function headers(): array
    {
        return [];
    }
}
