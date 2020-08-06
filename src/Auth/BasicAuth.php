<?php

declare(strict_types=1);

namespace Sigmie\Auth;

use Sigmie\Contracts\Authorizer;

class BasicAuth implements Authorizer
{
    private string $username;

    private string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;

        $this->password = $password;
    }

    public function headers(): array
    {
        return ['Authorization' => "Basic " . base64_encode("{$this->username}:{$this->password}")];
    }
}
