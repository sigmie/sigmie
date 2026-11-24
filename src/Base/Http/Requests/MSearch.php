<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Requests;

use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Base\Http\NdJSONRequest;

class MSearch extends NdJSONRequest
{
    public function response(ResponseInterface $psr): ElasticsearchResponse
    {
        return ElasticsearchResponse::fromPsrResponse($psr);
    }
}
