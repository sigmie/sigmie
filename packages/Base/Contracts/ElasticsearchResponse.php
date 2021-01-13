<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Exception;
use GuzzleHttp\Psr7\Request;

interface ElasticsearchException
{
    public function exception(Request $request): Exception;
}