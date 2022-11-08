<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Requests;

use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Http\NdJSONRequest;
use Sigmie\Base\Http\ElasticsearchResponse;

class MSearch extends NdJSONRequest implements ElasticsearchRequest
{
    public function response(ResponseInterface $psr): ElasticsearchResponse
    {
        return new ElasticsearchResponse($psr);
    }
}
