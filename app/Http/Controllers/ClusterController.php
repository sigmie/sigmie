<?php

namespace App\Http\Controllers;

use App\Cluster;
use App\Project;
use Inertia\Inertia;
use App\Jobs\CreateCluster;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCluster;
use Sigmie\App\Core\ClusterManager;
use App\Facades\Cluster as FacadesCluster;
use App\Factories\ClusterManagerFactory;
use App\Jobs\DestroyCluster;
use App\Notifications\ClusterCreated;
use App\Notifications\ClusterWasDestroyed;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Pusher\Pusher;
use Sigmie\App\Core\Cluster as CoreCluster;

class ClusterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
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

    /**
     * Display the specified resource.
     *
     * @param  \App\Cluster  $cluster
     * @return \Illuminate\Http\Response
     */
    public function show(Cluster $cluster)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Cluster  $cluster
     * @return \Illuminate\Http\Response
     */
    public function edit(Cluster $cluster)
    {
        dd($cluster);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Cluster  $cluster
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Cluster $cluster)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Cluster  $cluster
     * @return \Illuminate\Http\Response
     */
    public function destroy(Project $project)
    {
        $cluster = $project->clusters->first();

        DestroyCluster::dispatch($cluster->id);

        $cluster->state = Cluster::QUEUED_DESTROY;
        $cluster->save();
        $cluster->delete();

        return redirect()->route('dashboard');
    }
}
