<?php

declare(strict_types=1);

namespace App\Http\Controllers\Proxy;

use App\Http\Middleware\Proxy\ProxyRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class ProxyController extends \App\Http\Controllers\Controller
{
    /**
     * Proxy pass the incoming request to the Elasticsearch
     */
    public function __invoke(ProxyRequest $proxyRequest, ServerRequestInterface $request, Client $client)
    {
        $cluster = $proxyRequest->cluster();
        $username = $cluster->getAttribute('username');
        $password = decrypt($cluster->getAttribute('password'));

        $tempUri = new Uri($cluster->getAttribute('url'));

        $options = [
            'auth' => [
                $username,
                $password
            ],
            'http_errors' => false,
        ];

        $uri = $request->getUri()
            ->withHost($tempUri->getHost())
            ->withPort($tempUri->getPort());

        $psrRequest = $request->withUri($uri);

        return $client->send($psrRequest, $options);
    }
}
