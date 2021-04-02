<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cluster;

use App\Events\Cluster\ClusterWasUpdated;
use App\Http\Requests\Cluster\StoreCluster;
use App\Http\Requests\Cluster\UpdateCluster;
use App\Jobs\Cluster\CreateCluster;
use App\Jobs\Cluster\DestroyCluster;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use App\Repositories\RegionRepository;
use Composer\InstalledVersions;
use Inertia\Inertia;
use App\Http\Requests\AllowedIpRequest;
use App\Http\Requests\Cluster\StoreAllowedIp;
use App\Http\Requests\Cluster\UpdateAllowedIp;
use App\Http\Requests\Cluster\UpdateBasicAuth;
use App\Jobs\Cluster\UpdateClusterAllowedIps;
use App\Jobs\Cluster\UpdateClusterBasicAuth;
use App\Models\AllowedIp;

class BasicAuthController extends \App\Http\Controllers\Controller
{
    public function update(Cluster $cluster, UpdateBasicAuth $request)
    {
        $this->authorize('update', $cluster);

        $data =  $request->validated();

        $cluster->update(
            [
                'username' => $data['username'],
                'password' => encrypt($data['username'])
            ],
        );

        $job = new UpdateClusterBasicAuth($cluster->id);

        dispatch($job);

        event(new ClusterWasUpdated($cluster->project->id));

        return redirect()->route('settings');
    }
}
