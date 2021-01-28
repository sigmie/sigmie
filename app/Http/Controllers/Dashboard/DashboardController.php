<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Sigmie\Base\APIs\Calls\Cat as CatAPI;

class DashboardController extends \App\Http\Controllers\Controller
{
    use CatAPI;

    private ClusterRepository $clusters;

    public function __construct(ClusterRepository $clusterRepository)
    {
        $this->clusters = $clusterRepository;
    }

    public function data(Project $project)
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
            $this->setHttpConnection($cluster->newHttpConnection());

            $catResponse = $this->catAPICall('/indices', 'GET');

            $health = $cluster->health();

            $clusterInfo = [
                'health' => $health['status'],
                'nodesCount' => $health['number_of_nodes'],
                'name' => $health['cluster_name'],
            ];


            $indices = collect($catResponse->json())
                ->map(fn ($values) => [
                    'name' => $values['index'],
                    'size' => $values['store.size'],
                    'docsCount' => $values['docs.count']
                ])->toArray();
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