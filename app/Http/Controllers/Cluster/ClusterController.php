<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cluster;

use function App\Helpers\app_core_version;
use App\Http\Requests\Cluster\StoreCluster;
use App\Http\Requests\Cluster\UpdateCluster;
use App\Jobs\Cluster\CreateCluster;
use App\Jobs\Cluster\DestroyCluster;
use App\Models\Cluster;
use App\Models\Project;

use App\Models\Region;

use Inertia\Inertia;

class ClusterController extends \App\Http\Controllers\Controller
{
    public function create()
    {
        $this->authorize('create', Cluster::class);

        return Inertia::render('cluster/create/create', ['regions' => Region::all()]);
    }

    public function store(StoreCluster $request)
    {
        $validated = $request->validated();

        $this->authorize('create', Cluster::class);

        $name = $validated['name'];
        $domain = config('services.cloudflare.domain');
        $project = Project::find($validated['project_id']);

        /** @var  Cluster $cluster */
        $cluster = Cluster::create([
            'name' => $name,
            'region_id' => $validated['region_id'],
            'project_id' => $validated['project_id'],
            'nodes_count' => $validated['nodes_count'],
            'username' => $validated['username'],
            'password' => encrypt($validated['password']),
            'memory' => $validated['memory'],
            'cores' => $validated['cores'],
            'disk' => $validated['disk'],
            'url' => "https://{$name}.{$domain}",
            'state' => Cluster::QUEUED_CREATE,
            'core_version' => app_core_version(),
        ]);

        $project->internalClusters()->attach($cluster);

        $clusterId = $cluster->getAttribute('id');

        $job = new CreateCluster($clusterId);

        dispatch($job);

        return redirect()->route('dashboard');
    }

    public function edit(Cluster $cluster)
    {
        $this->authorize('update', $cluster);

        return Inertia::render('cluster/edit/edit', [
            'regions' => Region::all(),
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
            'core_version' => app_core_version(),
            'memory' => $validated['memory'],
            'cores' => $validated['cores'],
            'disk' => $validated['disk'],
        ]);

        $cluster->restore();

        $job = new CreateCluster($cluster->id);

        dispatch($job);

        return redirect()->route('dashboard');
    }

    public function destroy(Cluster $cluster)
    {
        $this->authorize('delete', $cluster);

        $job = new DestroyCluster($cluster->id);

        dispatch($job);

        $cluster->update(['state' => Cluster::QUEUED_DESTROY]);

        $cluster->delete();

        return redirect()->route('dashboard');
    }
}
