<?php

declare(strict_types=1);

namespace App\Http\Controllers\Proxy;

use App\Http\Middleware\Proxy\ProxyRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ServerRequestInterface;

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
            ->withPort($tempUri->getPort())
            ->withScheme($tempUri->getScheme());

        $psrRequest = $request->withUri($uri);

        foreach ($psrRequest->getHeaders() as $header => $value) {
            $psrRequest = $psrRequest->withoutHeader($header);
        }

        $psrRequest = $psrRequest->withAddedHeader('Content-Type', 'application/json');

        return $client->send($psrRequest, $options);
    }
}
