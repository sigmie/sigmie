<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cluster;

use App\Events\Cluster\ClusterWasUpdated;
use App\Http\Requests\Cluster\UpdateBasicAuth;
use App\Jobs\Cluster\UpdateClusterBasicAuth;
use App\Models\Cluster;

class BasicAuthController extends \App\Http\Controllers\Controller
{
    public function update(Cluster $cluster, UpdateBasicAuth $request)
    {
        $this->authorize('update', $cluster);

        $data =  $request->validated();

        $cluster->update(
            [
                'username' => $data['username'],
                'password' => encrypt($data['password'])
            ],
        );

        $job = new UpdateClusterBasicAuth($cluster->id);

        dispatch($job);

        event(new ClusterWasUpdated($cluster->project->id));

        return redirect()->route('settings');
    }
}
