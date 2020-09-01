<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\ProxyRequest;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ProxyController extends Controller
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
        $domain = config('services.cloudflare.domain');

        $url = (App::runningUnitTests()) ? 'http://es:9200/' : "https://{$cluster->name}.{$domain}/";

        $options = [
            'auth' => [$username, $password],
            'http_errors' => false,
        ];

        if ($request->isJson()) {
            $options['json'] = $request->toArray();
        }

        $response = $client->$method($url . $endpoint, $options);

        return $response->getBody()->getContents();
    }
}
