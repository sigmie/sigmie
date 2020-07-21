<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class ProxyController extends Controller
{
    /**
     * Proxy pass the incoming request to the Elasticsearch
     */
    public function __invoke(Cluster $cluster, string $endpoint = '', Request $request, Client $client)
    {
        $headers = $request->headers->all();
        $method = strtolower($request->method());
        $url = 'http://es:9200/';

        $value = $request->toArray();

        $response = $client->$method($url . $endpoint, [
            'headers' => $headers,
            'json' => $value,
        ]);

        return $response->getBody()->getContents();
    }
}
