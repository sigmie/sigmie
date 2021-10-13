<?php

declare(strict_types=1);

namespace Sigmie\Http\Contracts;

use Psr\Http\Message\ResponseInterface;

interface JSONResponse
{
    public function failed(): bool;

    public function json(null|string|int $key = null): int|bool|string|array|null;

    public function psr(): ResponseInterface;
}
