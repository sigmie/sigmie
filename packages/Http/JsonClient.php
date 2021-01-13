<?php

declare(strict_types=1);

namespace Sigmie\Http;

use GuzzleHttp\Client as GuzzleHttpClient;
use Sigmie\Http\Contracts\Auth;
use Sigmie\Http\Contracts\JsonClient as JsonClientInterface;
use Sigmie\Http\Contracts\JsonRequest;

class JsonClient implements JsonClientInterface
{
    protected GuzzleHttpClient $http;

    public function __construct(GuzzleHttpClient $http)
    {
        $this->http = $http;
    }

    public function request(JsonRequest $jsonRequest): JsonResponse
    {
        $psrResponse = $this->http->send($jsonRequest);

        return new JsonResponse($psrResponse);
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
