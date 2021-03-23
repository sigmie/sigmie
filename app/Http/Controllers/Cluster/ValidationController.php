<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cluster;

use App\Http\Controllers\Controller;
use App\Models\Cluster;
use App\Models\ExternalCluster;
use App\Repositories\ClusterRepository;
use App\Rules\UniqueClusterName;

class ValidationController extends Controller
{
    public function name(string $name)
    {
        $rule = new UniqueClusterName;

        $valid = $rule->passes('name', $name);

        return response()->json(['valid' => $valid]);
    }
}
