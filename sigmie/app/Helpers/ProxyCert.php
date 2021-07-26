<?php

declare(strict_types=1);

namespace App\Helpers;

use Sigmie\Http\Contracts\Auth;

class ProxyCert implements Auth
{
    public function keys(): array
    {
        return [
            'verify' => false,
            'cert' => storage_path('app/proxy/proxy.crt'),
            'ssl_key' => storage_path('app/proxy/proxy.key')
        ];
    }
}
