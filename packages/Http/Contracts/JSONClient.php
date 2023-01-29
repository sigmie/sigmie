<?php

declare(strict_types=1);

namespace Sigmie\Http\Contracts;

use Http\Promise\Promise;
use Sigmie\Http\JSONResponse;

interface JSONClient
{
    public function request(JSONRequest $request): JSONResponse;

    public function promise(JSONRequest $request): Promise;
}
