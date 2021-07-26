<?php



declare(strict_types=1);

namespace Sigmie\Base\Http;

use Psr\Http\Message\ResponseInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest as ElasticsearchRequestInterface;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseInterface;
use Sigmie\Http\JSONRequest;

class ElasticsearchRequest extends JSONRequest implements ElasticsearchRequestInterface
{
    public function response(ResponseInterface $psr): ElasticsearchResponseInterface
    {
        return new ElasticsearchResponse($psr);
    }
}
