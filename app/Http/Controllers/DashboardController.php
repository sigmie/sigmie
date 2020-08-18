<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Sigmie\Search\SigmieClient;

class DashboardController extends Controller
{
    private ClusterRepository $clusters;

    public function __construct(ClusterRepository $clusterRepository)
    {
        $this->clusters = $clusterRepository;
    }

    public function __invoke(Request $request, Project $project, SigmieClient $sigmieClient)
    {
        Gate::authorize('view-dashboard', $project);

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
            $clusterInfo = $sigmieClient->cluster()->get();
            $indices = $sigmieClient->indices()->list()->toArray();

            $indices = array_map(fn ($index) => (array) $index, $indices);
        }

        return Inertia::render('dashboard/dashboard', [
            'clusterState' => $state,
            'clusterId' => $id,
            'indices' => $indices,
            'clusterInfo' => $clusterInfo
        ]);
    }
}
