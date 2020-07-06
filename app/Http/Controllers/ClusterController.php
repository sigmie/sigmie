<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCluster;
use App\Jobs\CreateCluster;
use App\Jobs\DestroyCluster;
use App\Models\Cluster;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ClusterController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Cluster::class, 'cluster');
    }

    public function index()
    {
        //
    }

    public function create(Request $request)
    {
        $projectId = $request->get('project_id');

        $project = Project::find($projectId);

        if (Gate::allows('create-cluster', $project) === false) {

            $cluster = $project->clusters()->withTrashed()->first();

            $id = route('cluster.edit', $cluster->id);

            return redirect()->route('cluster.edit', $cluster->id);
        }

        return Inertia::render('cluster/create');
    }

    public function store(StoreCluster $request)
    {
        $projectId = $request->get('project_id');

        $project = Project::find($projectId);

        if (Gate::allows('create-cluster', $project) === false) {
            return redirect()->route('dashboard');
        }

        $values = $request->all();

        $cluster = Cluster::create([
            'name' => $values['name'],
            'data_center' => $values['dataCenter'],
            'project_id' => $values['project_id'],
            'nodes_count' => $values['nodes_count'],
            'username' => $values['username'],
            'password' => encrypt($values['password']),
            'state' => Cluster::QUEUED_CREATE
        ]);

        CreateCluster::dispatch($cluster->id);

        return redirect()->route('dashboard');
    }

    public function show(Cluster $cluster)
    {
        //
    }

    public function edit(Request $request, Cluster $cluster)
    {
        return Inertia::render('cluster/edit', ['cluster' =>
        [
            'id' => $cluster->id,
            'name' => $cluster->name
        ]]);
    }

    public function update(Request $request, Cluster $cluster)
    {
        $values = $request->all();

        $cluster->update([
            'data_center' => $values['dataCenter'],
            'nodes_count' => $values['nodes_count'],
            'username' => $values['username'],
            'password' =>  encrypt($values['password']),
            'state' => Cluster::QUEUED_CREATE
        ]);

        $cluster->restore();

        CreateCluster::dispatch($cluster->id);

        return redirect()->route('dashboard');
    }

    public function destroy(Cluster $cluster)
    {
        DestroyCluster::dispatch($cluster->id);

        $cluster->update(['state' => Cluster::QUEUED_DESTROY]);

        $cluster->delete();

        return redirect()->route('dashboard');
    }
}
