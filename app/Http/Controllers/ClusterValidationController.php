<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cluster;
use App\Repositories\ClusterRepository;

class UserValidationController extends Controller
{
    private $clusters;

    public function __construct(ClusterRepository $clusterRepository)
    {
        $this->clusters = $clusterRepository;
    }

    public function name(string $name)
    {
        $cluster = $this->clusters->findOneTrashedBy('name', $name);

        $valid = ($cluster instanceof Cluster) ? false : true;

        return response()->json(['valid' => $valid]);
    }
}
