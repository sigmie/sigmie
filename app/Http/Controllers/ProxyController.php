<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use Illuminate\Support\Facades\Http;

class ProxyController extends Controller
{
    /**
     * Proxy pass the incoming request to the Elasticsearch
     */
    public function __invoke(string $endpoint, Cluster $cluster)
    {
        $url = "http://es:9200/";

        dispatch(fn () => dump('log the request'));

        $response = Http::withBasicAuth('', '')->timeout(3)->get($url . $endpoint);

        dispatch(fn () => dump('log the response'));

        return $response->json();
    }
}
