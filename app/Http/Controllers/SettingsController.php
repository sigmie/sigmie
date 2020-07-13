<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    private $projects;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projects = $projectRepository;
    }
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index(Request $request)
    {
        $project = $this->projects->find($request->get('project_id'));

        $cluster = $project->getAttribute('clusters')->first();
        $clusterId = null;

        if ($cluster !== null) {
            $clusterId = $cluster->getAttribute('id');
        }

        return Inertia::render('settings/index', ['clusterId' => $clusterId]);
    }
}
