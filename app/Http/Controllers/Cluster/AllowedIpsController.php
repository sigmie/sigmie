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
use App\Http\Requests\AllowedIpRequest;
use App\Http\Requests\Cluster\StoreAllowedIp;
use App\Http\Requests\Cluster\UpdateAllowedIp;
use App\Jobs\Cluster\UpdateClusterAllowedIps;
use App\Models\AllowedIp;

class AllowedIpsController extends \App\Http\Controllers\Controller
{
    public function store(Cluster $cluster, StoreAllowedIp $request)
    {
        $cluster->allowedIps()->create(
            $request->validated()
        );

        $cluster->dispatchUpdateAllowedIps();

        return redirect()->route('settings');
    }

    public function update(Cluster $cluster, AllowedIp $address, UpdateAllowedIp $request)
    {
        $data =  $request->validated();

        $shouldUpdate = $data['ip'] !== $address->ip;

        $address->update($data);

        // If the Ip has been updated dispatch job
        if ($shouldUpdate) {
            $cluster->dispatchUpdateAllowedIps();
        }

        return redirect()->route('settings');
    }

    public function destroy(Cluster $cluster, AllowedIp $address)
    {
        $address->delete();

        $cluster->dispatchUpdateAllowedIps();

        return redirect()->route('settings');
    }
}
