<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Exception;
use GuzzleHttp\Psr7\Request;
use Sigmie\Http\Contracts\JSONResponse;

interface ElasticsearchResponse extends JSONResponse
{
    public function exception(ElasticsearchRequest $request): Exception;
}
