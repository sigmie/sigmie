<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DashboardController extends Controller
{
    private $projects;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projects = $projectRepository;
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Project $project)
    {
        Gate::authorize('view-dashboard', $project);

        $state = $project->getAttribute('state');
        $id = $project->getAttribute('id');

        return Inertia::render('dashboard', ['state' => $state, 'id' => $id]);
    }

    private function userHasProjects()
    {
        return Auth::user()->projects->isEmpty() === false;
    }
}
