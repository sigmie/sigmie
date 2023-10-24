<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sigmie\Http\Contracts\JSONRequest;

interface ElasticsearchRequest extends RequestInterface, JSONRequest
{
    public function response(ResponseInterface $psr): ElasticsearchResponse;
}
