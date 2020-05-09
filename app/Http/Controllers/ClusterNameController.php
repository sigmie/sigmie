<?php

namespace App\Http\Controllers;

use App\Cluster;

class ClusterNameController extends Controller
{
    public function show(Cluster $foo)
    {
        dd($foo);
    }
}
