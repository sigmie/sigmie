<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cluster;

use App\Events\Cluster\ClusterWasUpdated;
use App\Http\Requests\Cluster\StoreAllowedIp;
use App\Http\Requests\Cluster\UpdateAllowedIp;
use App\Jobs\Cluster\UpdateClusterAllowedIps;
use App\Models\AllowedIp;
use App\Models\Cluster;

class AllowedIpsController extends \App\Http\Controllers\Controller
{
    public function store(Cluster $cluster, StoreAllowedIp $request)
    {
        $this->authorize('update', $cluster);

        $cluster->allowedIps()->create(
            $request->validated()
        );

        $job = new UpdateClusterAllowedIps($cluster->id);

        dispatch($job);

        event(new ClusterWasUpdated($cluster->project->id));

        return redirect()->route('settings');
    }

    public function update(Cluster $cluster, AllowedIp $address, UpdateAllowedIp $request)
    {
        $this->authorize('update', $cluster);

        $data =  $request->validated();

        $shouldUpdate = $data['ip'] !== $address->ip;

        $address->update($data);

        // If the Ip has been updated dispatch job
        if ($shouldUpdate) {
            $job = new UpdateClusterAllowedIps($cluster->id);

            dispatch($job);
        }

        event(new ClusterWasUpdated($cluster->project->id));

        return redirect()->route('settings');
    }

    public function destroy(Cluster $cluster, AllowedIp $address)
    {
        $this->authorize('update', $cluster);

        $address->delete();

        dispatch(new UpdateClusterAllowedIps($cluster->id));

        event(new ClusterWasUpdated($cluster->project->id));

        return redirect()->route('settings');
    }
}
