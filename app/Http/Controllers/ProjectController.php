<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProject;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProjectController extends Controller
{
    private ProjectRepository $projects;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projects = $projectRepository;

        $this->authorizeResource(Project::class, 'project');
    }

    public function create()
    {
        return Inertia::render('project/create');
    }

    public function store(StoreProject $request)
    {
        $validated = $request->validated();
        $credentials = json_decode($validated['provider']['creds'], true);
        $provider = $validated['provider']['id'];
        $userId = Auth::user()->getAttribute('id');

        $this->projects->create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'creds' => encrypt($credentials),
            'provider' => $provider,
            'user_id' => $userId
        ]);

        return redirect()->route('cluster.create');
    }
}
