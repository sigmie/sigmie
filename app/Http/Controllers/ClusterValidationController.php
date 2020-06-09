<?php

namespace App\Http\Controllers;

use App\Cluster;
use App\Rules\ValidProvider;
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
        $valid = Cluster::firstWhere('name', $name) === null;

        return response()->json(['valid' => $valid]);
    }
}
