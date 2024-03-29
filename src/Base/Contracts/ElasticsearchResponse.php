<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Exception;
use Sigmie\Http\Contracts\JSONResponse;

interface ElasticsearchResponse extends JSONResponse
{
    public function exception(ElasticsearchRequest $request): Exception;

    public function code(): int;
}
