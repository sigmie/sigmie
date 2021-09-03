<?php

declare(strict_types=1);

namespace Sigmie\Http;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\UriInterface as Uri;
use Sigmie\Http\Contracts\JSONRequest as JSONRequestInterface;

class JSONRequest extends Request implements JSONRequestInterface
{
    protected string|null $body;

    protected array $headers = [
        'Content-type' => 'application/json',
    ];

    public function __construct(string $method, Uri $uri, ?array $body = null)
    {
        $this->body = is_null($body) ? $body : json_encode($body);

        ray($this->body());
        
        parent::__construct($method, $uri, $this->headers, $this->body);
    }

    public function body(): ?array
    {
        return $this->body ? json_decode($this->body, true) : null;
    }
}
