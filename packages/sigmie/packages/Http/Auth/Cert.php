<?php

declare(strict_types=1);

namespace Sigmie\Http\Auth;

use Sigmie\Http\Contracts\Auth;

final class Cert implements Auth
{
    protected $path;

    protected $password;

    public function __construct(string $path, string $password)
    {
        $this->path = $path;
        $this->password = $password;
    }

    public function key(): string
    {
        return 'cert';
    }

    public function value()
    {
        return [$this->path, $this->password];
    }
}
