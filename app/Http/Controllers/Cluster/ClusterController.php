<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cluster;

use App\Http\Requests\Cluster\StoreCluster;
use App\Http\Requests\Cluster\UpdateCluster;
use App\Jobs\Cluster\CreateCluster;
use App\Jobs\Cluster\DestroyCluster;
use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use App\Repositories\RegionRepository;
use Composer\InstalledVersions;
use Inertia\Inertia;

class ClusterController extends \App\Http\Controllers\Controller
{
    public function create(RegionRepository $regions)
    {
        $this->authorize('create', Cluster::class);

        return Inertia::render('cluster/create/create', ['regions' => $regions->all()]);
    }

    public function store(StoreCluster $request)
    {
        $validated = $request->validated();

        $this->authorize('create', Cluster::class);

        $name = $validated['name'];
        $domain = config('services.cloudflare.domain');
        $project = Project::find($validated['project_id']);

        $cluster = Cluster::create([
            'name' => $name,
            'region_id' => $validated['region_id'],
            'project_id' => $validated['project_id'],
            'nodes_count' => $validated['nodes_count'],
            'username' => $validated['username'],
            'password' => encrypt($validated['password']),
            'url' => "https://{$name}.{$domain}",
            'state' => Cluster::QUEUED_CREATE,
            'core_version' => app_core_version()
        ]);

        $project->internalClusters()->attach($cluster);

        $clusterId = $cluster->getAttribute('id');

        CreateCluster::dispatch($clusterId, [
            'memory' => $validated['memory'],
            'cores' => $validated['cores'],
            'disk' => $validated['disk'],
        ]);

        return redirect()->route('dashboard');
    }

    public function edit(Cluster $cluster, RegionRepository $regions)
    {
        $this->authorize('update', $cluster);

        return Inertia::render('cluster/edit/edit', [
            'regions' => $regions->all(),
            'cluster' => [
                'id' => $cluster->getAttribute('id'),
                'name' => $cluster->getAttribute('name')
            ]
        ]);
    }

    public function update(UpdateCluster $request, Cluster $cluster)
    {
        $validated = $request->validated();

        $this->authorize('restore', $cluster);

        $cluster->update([
            'region_id' => $validated['region_id'],
            'nodes_count' => $validated['nodes_count'],
            'username' => $validated['username'],
            'password' =>  encrypt($validated['password']),
            'state' => Cluster::QUEUED_CREATE,
            'core_version' => app_core_version()
        ]);

        $cluster->restore();

        CreateCluster::dispatch($cluster->id, [
            'memory' => $validated['memory'],
            'cores' => $validated['cores'],
            'disk' => $validated['disk'],
        ]);

        return redirect()->route('dashboard');
    }

    public function destroy(Cluster $cluster)
    {
        $this->authorize('delete', $cluster);

        DestroyCluster::dispatch($cluster->id);

        $cluster->update(['state' => Cluster::QUEUED_DESTROY]);

        $cluster->delete();

        return redirect()->route('dashboard');
    }
}
