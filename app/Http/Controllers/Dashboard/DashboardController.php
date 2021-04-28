<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Models\Cluster;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Sigmie\Base\APIs\Calls\Cat as CatAPI;

class DashboardController extends \App\Http\Controllers\Controller
{
    use CatAPI;

    public function data(Project $project)
    {
        $cluster =  $project->clusters->first();

        $id = null;
        $state = null;
        $indices = null;
        $clusterInfo = null;

        if ($cluster !== null) {
            $state = $cluster->getAttribute('state');
            $id = $cluster->getAttribute('id');
        }

        if ($cluster->getAttribute('state') === Cluster::RUNNING) {

            $this->setHttpConnection($cluster->newHttpConnection());

            $health = $cluster->health();

            $clusterInfo = [
                'health' => $health['status'],
                'nodesCount' => $health['number_of_nodes'],
                'name' => $health['cluster_name'],
            ];

            $indices = $cluster->aliases();
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
        $cluster = $project->clusters->first();

        Gate::authorize('viewDashboard', $project);

        return Inertia::render('dashboard/dashboard', ['clusterId' => $cluster->getAttribute('id')]);
    }
}
