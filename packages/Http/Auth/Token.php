<?php

declare(strict_types=1);

namespace Sigmie\Http\Auth;

use Sigmie\Http\Contracts\Auth;

final class Token implements Auth
{
    private string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function keys(): array
    {
        return [
            'headers' => ['Authorization' => "Bearer {$this->token}"],
        ];
    }
}
