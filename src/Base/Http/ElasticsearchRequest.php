<?php

declare(strict_types=1);

namespace Sigmie\Base\Http;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Sigmie\Base\Contracts\ElasticsearchRequest as ElasticsearchRequestInterface;
use Sigmie\Base\Contracts\ElasticsearchResponse as ElasticsearchResponseInterface;

class ElasticsearchRequest extends Request implements ElasticsearchRequestInterface
{
    public function __construct(string $method, UriInterface $uri, ?array $body = null)
    {
        $json = $body === null ? null : json_encode((object) $body, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        parent::__construct($method, $uri, ['Content-type' => 'application/json'], $json);
    }

    public function body(): ?array
    {
        $body = json_decode((string) $this->getBody(), true, flags: JSON_BIGINT_AS_STRING);

        return is_array($body) ? $body : null;
    }

    public function response(ResponseInterface $psr): ElasticsearchResponseInterface
    {
        return ElasticsearchResponse::fromPsrResponse($psr);
    }
}
