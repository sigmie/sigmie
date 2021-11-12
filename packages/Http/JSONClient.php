<?php

declare(strict_types=1);

namespace Sigmie\Http;

use GuzzleHttp\Client as GuzzleHttpClient;
use Sigmie\Http\Contracts\Auth;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;
use Sigmie\Http\Contracts\JSONRequest;

class JSONClient implements JSONClientInterface
{
    protected GuzzleHttpClient $http;

    public function __construct(GuzzleHttpClient $http)
    {
        $this->http = $http;
    }

    public function request(JSONRequest $jsonRequest, array $options = []): JSONResponse
    {
        $psrResponse = $this->http->send($jsonRequest, $options);

        return new JSONResponse($psrResponse);
    }

    public static function create(string $url, ?Auth $auth = null): static
    {
        $config = [
            'base_uri' => $url,
            //'allow_redirects' => false,
            'http_errors' => false,
            //'connect_timeout' => 1
        ];

        if (is_null($auth) === false) {
            $config = array_merge($config, $auth->keys());
        }

        $client = new GuzzleHttpClient($config);

        return new static($client);
    }
}
