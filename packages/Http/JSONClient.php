<?php

declare(strict_types=1);

namespace Sigmie\Http;

use Elastic\Transport\Transport;
use Elastic\Transport\TransportBuilder;
use GuzzleHttp\Client as GuzzleHttpClient;
use Sigmie\Http\Contracts\Auth;
use Sigmie\Http\Contracts\JSONClient as JSONClientInterface;
use Sigmie\Http\Contracts\JSONRequest;

class JSONClient implements JSONClientInterface
{
    public function __construct(protected Transport $http)
    {
    }

    public function request(JSONRequest $jsonRequest): JSONResponse
    {
        $psrResponse = $this->http->sendRequest($jsonRequest);

        return new JSONResponse($psrResponse);
    }

    public static function create(array|string $hosts, ?Auth $auth = null): static
    {
        $hosts = (is_string($hosts)) ? explode(',', $hosts) : $hosts;

        $config = [
            'curl' => [
                CURLOPT_RESOLVE => [
                    'fakedomain.dev:80:127.0.0.1'
                ]
            ],
            'allow_redirects' => false,
            'http_errors' => false,
            'connect_timeout' => 15,
        ];

        if (is_null($auth) === false) {
            $config = array_merge($config, $auth->keys());
        }

        $client = new GuzzleHttpClient($config);

        $transport = TransportBuilder::create()
            ->setHosts($hosts)
            ->setClient($client)
            ->build();

        return new static($transport);
    }
}
