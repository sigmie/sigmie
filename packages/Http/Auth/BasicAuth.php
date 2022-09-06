<?php

declare(strict_types=1);

namespace Sigmie\Http\Auth;

use Sigmie\Http\Contracts\Auth;

final class BasicAuth implements Auth
{
    private string $username;

    private string $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;

        $this->password = $password;
    }

    public function keys(): array
    {
        return ['auth' => [
            $this->username,
            $this->password
        ]];
    }
}
