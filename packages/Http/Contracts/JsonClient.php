<?php

declare(strict_types=1);

namespace Sigmie\Http\Contracts;

use Sigmie\Http\JsonRequest;
use Sigmie\Http\JsonResponse;

interface JsonClient
{
    public function request(JsonRequest $request): JsonResponse;
}
