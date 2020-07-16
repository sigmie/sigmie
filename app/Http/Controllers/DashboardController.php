<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Repositories\ClusterRepository;
use App\Repositories\ProjectRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DashboardController extends Controller
{
    private ClusterRepository $clusters;

    public function __construct(ClusterRepository $clusterRepository)
    {
        $this->clusters = $clusterRepository;
    }
    /**
     * Get the project state and id and render
     * the dashboard view
     */
    public function __invoke(Request $request, Project $project)
    {
        Gate::authorize('view-dashboard', $project);

        $cluster = $this->clusters->findOneTrashedBy('project_id', (string) $project->getAttribute('id'));
        $id = null;
        $state = null;

        if ($cluster !== null) {
            $state = $cluster->getAttribute('state');
            $id = $cluster->getAttribute('id');
        }

        return Inertia::render('dashboard', ['clusterState' => $state, 'clusterId' => $id]);
    }
}
