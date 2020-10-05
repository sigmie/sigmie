<?php

declare(strict_types=1);

namespace App\Http\Controllers\Proxy;

use App\Http\Middleware\Proxy\ProxyRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ProxyController extends \App\Http\Controllers\Controller
{
    /**
     * Proxy pass the incoming request to the Elasticsearch
     */
    public function __invoke(ProxyRequest $proxyRequest, Request $request, Client $client, string $endpoint = '')
    {
        $cluster = $proxyRequest->cluster();
        $headers = $request->headers->all();
        $method = strtolower($request->method());
        $username = $cluster->getAttribute('username');
        $password = decrypt($cluster->getAttribute('password'));

        $url = $cluster->getAttribute('url');

        $options = [
            'auth' => [$username, $password],
            'http_errors' => false,
        ];

        if ($request->isJson()) {
            $options['json'] = $request->toArray();
        }

        $url = $url . '/' . $endpoint . '?' . $request->getQueryString();

        /** @var  Response */
        $response = $client->$method($url, $options);

        $headers = $response->getHeaders();
        $contents = $response->getBody()->getContents();
        $code = $response->getStatusCode();

        return response($contents, $code, $headers);
    }
}
