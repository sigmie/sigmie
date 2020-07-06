<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use App\Models\Project;
use Inertia\Inertia;
use App\Jobs\CreateCluster;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCluster;
use Sigmie\App\Core\ClusterManager;
use App\Facades\Cluster as FacadesCluster;
use App\Helpers\ClusterManagerFactory;
use App\Jobs\DestroyCluster;
use App\Notifications\ClusterIsRunning;
use App\Notifications\ClusterWasDestroyed;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Pusher\Pusher;
use Sigmie\App\Core\Cluster as CoreCluster;

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
