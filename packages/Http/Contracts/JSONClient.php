<?php

declare(strict_types=1);

namespace Sigmie\Http\Contracts;

use Sigmie\Http\JSONResponse;
use Sigmie\Http\Contracts\JSONRequest;

interface JSONClient
{
    public function request(JSONRequest $request): JSONResponse;
}
