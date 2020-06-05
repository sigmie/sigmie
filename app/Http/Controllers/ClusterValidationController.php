<?php

namespace App\Http\Controllers;

use App\Cluster;

class ClusterValidationController extends Controller
{
    public function name(string $name)
    {
        $valid = Cluster::firstWhere('name', $name) === null;

        return response()->json(['valid' => $valid]);
    }
}
