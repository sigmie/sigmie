<?php

namespace App\Http\Controllers;

use App\Cluster;
use App\Events\ClusterDestroyed;
use App\Project;
use Inertia\Inertia;
use App\Jobs\CreateCluster;
use Illuminate\Http\Request;
use App\Http\Requests\StoreCluster;
use Sigmie\App\Core\ClusterManager;
use App\Facades\Cluster as FacadesCluster;
use App\Factories\ClusterManagerFactory;
use App\Notifications\ClusterCreated;
use App\Notifications\ClusterWasDestroyed;
use App\User;
use Illuminate\Support\Facades\Auth;
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
        $values = $request->all();

        $cluster = Cluster::create([
            'name' => $values['name'],
            'data_center' => $values['dataCenter'],
            'project_id' => $values['project_id'],
            'username' => $values['username'],
            'password' => '',
            'state' => Cluster::QUEUED
        ]);

        $project = Project::find($request->get('project_id'));

        // CreateCluster::dispatch($cluster->id);

        // Auth::user()->notify(new ClusterCreated($project->name, $request->get('name')));

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
        //
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
        Auth::user()->notify(new ClusterWasDestroyed($project->name));
    }
}
