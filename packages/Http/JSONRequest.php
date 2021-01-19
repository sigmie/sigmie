<?php

declare(strict_types=1);

namespace Sigmie\Http;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Sigmie\Http\Contracts\JSONRequest as JSONRequestInterface;

class JSONRequest extends Request implements JSONRequestInterface
{
    protected $headers = [
        'Content-type' => 'application/json',
    ];

    public function __construct(string $method, Uri $uri, ?array $body = null)
    {
        $body = is_null($body) ? $body : json_encode($body);

        parent::__construct($method, $uri, $this->headers, $body);
    }
}
