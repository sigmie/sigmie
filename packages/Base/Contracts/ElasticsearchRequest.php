<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ElasticsearchRequest extends RequestInterface
{
    public function response(ResponseInterface $psr): ElasticsearchResponse;
}
