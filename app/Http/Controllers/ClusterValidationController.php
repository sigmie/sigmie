<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use Google_Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Sigmie\App\Core\Cloud\Providers\Google\Google;
use Google_Service_Compute;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

class ClusterValidationController extends Controller
{
    public function name(string $name)
    {
        /** @var  Cluster|null */
        $cluster = Cluster::withTrashed()->firstWhere('name', $name);

        $valid = ($cluster instanceof Cluster) ? true : false;

        return response()->json(['valid' => $valid]);
    }
}
