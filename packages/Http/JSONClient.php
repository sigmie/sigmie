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

    public function request(JSONRequest $jsonRequest): JSONResponse
    {
        $psrResponse = $this->http->send($jsonRequest);

        return new JSONResponse($psrResponse);
    }

    public static function create($url, ?Auth $auth = null)
    {
        $config = [
            'base_uri' => $url,
            'allow_redirects' => false,
            'http_errors' => false,
        ];

        if (is_null($auth) === false) {
            $config[$auth->key()] = $auth->value();
        }

        $client = new GuzzleHttpClient($config);

        return new static($client);
    }
}
