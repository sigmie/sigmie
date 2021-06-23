<?php

declare(strict_types=1);

namespace Sigmie\Http\Contracts;

use GuzzleHttp\Psr7\Response;

interface JSONResponse
{
    public function failed(): bool;

    public function json($key = null): int|bool|string|array|null;

    public function psr(): Response;
}
