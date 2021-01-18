<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use App\Services\Sigmie;
use Exception;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DashboardController extends \App\Http\Controllers\Controller
{
    private ClusterRepository $clusters;

    public function __construct(ClusterRepository $clusterRepository)
    {
        $this->clusters = $clusterRepository;
    }

    public function data(Project $project, Sigmie $sigmie)
    {
        $cluster = $this->clusters->findOneTrashedBy('project_id', (string) $project->getAttribute('id'));

        $id = null;
        $state = null;
        $indices = null;
        $clusterInfo = null;

        if ($cluster !== null) {
            $state = $cluster->getAttribute('state');
            $id = $cluster->getAttribute('id');
        }

        if ($cluster->getAttribute('state') === Cluster::RUNNING) {

            $indices = $cluster->indices()->toArray();
            $health = $cluster->health();

            $clusterInfo = [
                'health' => $health['status'],
                'nodesCount' => $health['number_of_nodes'],
                'name' => $health['cluster_name']
            ];
            // $indices = $sigmieClient->indices()->list()->toArray();

            // $indices = array_map(fn ($index) => (array) $index, $indices);
        }

        return [
            'clusterState' => $state,
            'clusterId' => $id,
            'indices' => $indices,
            'clusterInfo' => $clusterInfo
        ];
    }

    public function show(Project $project)
    {
        $cluster = $this->clusters->findOneTrashedBy('project_id', (string) $project->getAttribute('id'));

        Gate::authorize('view-dashboard', $project);

        return Inertia::render('dashboard/dashboard', ['clusterId' => $cluster->getAttribute('id')]);
    }
}
