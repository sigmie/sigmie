<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cluster;

use App\Http\Requests\Cluster\StoreCluster;
use App\Http\Requests\Cluster\UpdateCluster;
use App\Jobs\Cluster\CreateCluster;
use App\Jobs\Cluster\DestroyCluster;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use App\Repositories\RegionRepository;
use Composer\InstalledVersions;
use Inertia\Inertia;

class AllowedIps extends \App\Http\Controllers\Controller
{
    public function update()
    {
        return;
    }
}
