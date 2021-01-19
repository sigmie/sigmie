<?php

declare(strict_types=1);

namespace Sigmie\Http\Auth;

use Sigmie\Http\Contracts\Auth;

final class Token implements Auth
{
    private $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function key(): string
    {
        return 'headers';
    }

    public function value()
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }
}
