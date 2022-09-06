<?php

declare(strict_types=1);

namespace Sigmie\Http\Auth;

use Sigmie\Http\Contracts\Auth;

final class Headers implements Auth
{
    private array $headers;

    public function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    public function keys(): array
    {
        return [
            'headers' => $this->headers,
        ];
    }
}
