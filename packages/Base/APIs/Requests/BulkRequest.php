<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs\Requests;

use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\APIs\Responses\Bulk;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Http\NdJSONRequest;

class BulkRequest extends NdJSONRequest implements ElasticsearchRequest
{
    public function response(ResponseInterface $psr): ElasticsearchResponse
    {
        return new Bulk($psr);
    }
}
