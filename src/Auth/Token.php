<?php

declare(strict_types=1);

namespace Sigmie\Auth;

use Sigmie\Contracts\Authorizer;

class Token implements Authorizer
{
    private $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function headers(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }
}
