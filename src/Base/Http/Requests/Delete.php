<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Requests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface as Uri;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest as HttpElasticsearchRequest;
use Sigmie\Base\Http\Responses\Delete as DeleteResponse;

class Delete extends HttpElasticsearchRequest implements ElasticsearchRequest
{
    public function __construct(Uri $uri)
    {
        parent::__construct('DELETE', $uri, body: null);
    }

    public function response(ResponseInterface $psr): ElasticsearchResponse
    {
        return DeleteResponse::fromPsrResponse($psr);
    }
}
