<?php

declare(strict_types=1);

namespace Sigmie\Http\Contracts;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

interface JSONResponse
{
    public function failed(): bool;

    public function json(string|int $key = null): int|bool|string|array|null;

    public function psr(): ResponseInterface;
}
