<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cluster;

use App\Http\Requests\StoreCluster;
use App\Http\Requests\UpdateCluster;
use App\Jobs\CreateCluster;
use App\Jobs\DestroyCluster;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use Inertia\Inertia;

class ClusterController extends \App\Http\Controllers\Controller
{
    private ClusterRepository $clusters;

    public function __construct(ClusterRepository $clusterRepository)
    {
        $this->clusters = $clusterRepository;

        $this->authorizeResource(Cluster::class, 'cluster');
    }

    public function create()
    {
        return Inertia::render('cluster/create');
    }

    public function store(StoreCluster $request)
    {
        $validated = $request->validated();

        $cluster = $this->clusters->create([
            'name' => $validated['name'],
            'data_center' => $validated['data_center'],
            'project_id' => $validated['project_id'],
            'nodes_count' => $validated['nodes_count'],
            'username' => $validated['username'],
            'password' => encrypt($validated['password']),
            'state' => Cluster::QUEUED_CREATE
        ]);

        CreateCluster::dispatch($cluster->getAttribute('id'));

        return redirect()->route('dashboard');
    }

    public function edit(Cluster $cluster)
    {
        return Inertia::render('cluster/edit', ['cluster' =>
        [
            'id' => $cluster->getAttribute('id'),
            'name' => $cluster->getAttribute('name')
        ]]);
    }

    public function update(UpdateCluster $request, Cluster $cluster)
    {
        $validated = $request->validated();
        $clusterId = $cluster->getAttribute('id');

        $this->clusters->updateTrashed($clusterId, [
            'data_center' => $validated['data_center'],
            'nodes_count' => $validated['nodes_count'],
            'username' => $validated['username'],
            'password' =>  encrypt($validated['password']),
            'state' => Cluster::QUEUED_CREATE
        ]);

        $this->clusters->restore($clusterId);

        CreateCluster::dispatch($clusterId);

        return redirect()->route('dashboard');
    }

    public function destroy(Cluster $cluster)
    {
        $clusterId = $cluster->getAttribute('id');

        DestroyCluster::dispatch($clusterId);

        $this->clusters->update($clusterId, ['state' => Cluster::QUEUED_DESTROY]);

        $this->clusters->delete($clusterId);

        return redirect()->route('dashboard');
    }
}
