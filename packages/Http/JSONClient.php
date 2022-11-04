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

    public static function createWithToken(
        array $hosts,
        string $token,
        array $config = []
    ): static
    {     return self::create($hosts, [
            'headers' => ['Authorization' => "Bearer {$token}"],
        ]);
    }

    public static function createWithBasic(
        array $hosts,
        string $username,
        string $password,
        array $config = []
    ): static {
        return self::create($hosts, [
            'auth' => [$username, $password],
        ]);
    }

    public static function createWithHeaders(
        array $hosts,
        array $headers,
        array $config = []
    ): static {
        return self::create($hosts, [
            'auth' => $headers,
        ]);
    }


    public static function create(array $hosts, array $config = []): static
    {
        $config = [
            'allow_redirects' => false,
            'http_errors' => false,
            'connect_timeout' => 15,
            ...$config
        ];

        $client = new GuzzleHttpClient($config);

        $transport = TransportBuilder::create()
            ->setHosts($hosts)
            ->setClient($client)
            ->build();

        return new static($transport);
    }
}
