<?php


declare(strict_types=1);

namespace Sigmie\Base\Http\Requests;

use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Http\ElasticsearchRequest as HttpElasticsearchRequest;
use Sigmie\Base\Http\Responses\Reindex as ReindexResponse;

class Reindex extends HttpElasticsearchRequest implements ElasticsearchRequest
{
    public function response(ResponseInterface $psr): ElasticsearchResponse
    {
        return new ReindexResponse($psr);
    }
}
