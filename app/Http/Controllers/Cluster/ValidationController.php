<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cluster;

use App\Models\Cluster;
use App\Repositories\ClusterRepository;

class ValidationController extends \App\Http\Controllers\Controller
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
