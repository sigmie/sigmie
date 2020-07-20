<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use Illuminate\Support\Facades\Http;

class ProxyController extends Controller
{
    /**
     * Proxy pass the incoming request to the Elasticsearch
     */
    public function __invoke(Cluster $cluster, string $endpoint = '')
    {
        return [];
    }
}
