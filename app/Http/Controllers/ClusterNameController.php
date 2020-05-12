<?php

namespace App\Http\Controllers;

use App\Cluster;

class ClusterNameController extends Controller
{
    public function show(Cluster $cluster)
    {
        if ($cluster->exists); {
            return response($cluster->name, 200);
        }

        return $this->response('Not found', 404);
    }
}
