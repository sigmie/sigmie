<?php

declare(strict_types=1);

namespace Sigmie\Base\Http\Requests;

use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Http\Responses\Bulk as BulkResponse;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Http\NdJSONRequest;

class Bulk extends NdJSONRequest implements ElasticsearchRequest
{
    public function response(ResponseInterface $psr): ElasticsearchResponse
    {
        return new BulkResponse($psr);
    }
}
