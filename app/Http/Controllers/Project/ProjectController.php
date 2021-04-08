<?php

declare(strict_types=1);

namespace App\Http\Controllers\Project;

use App\Events\Project\ProjectWasUpdated;
use App\Http\Requests\Project\StoreProject;
use App\Http\Requests\Project\UpdateProject;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProjectController extends \App\Http\Controllers\Controller
{
    public function __construct()
    {
        $this->authorizeResource(Project::class, 'project');
    }

    public function create()
    {
        return Inertia::render('project/create/create');
    }

    public function store(StoreProject $request)
    {
        $validated = $request->validated();
        $credentials = json_decode($validated['provider']['creds'], true);
        $provider = $validated['provider']['id'];
        $userId = Auth::user()->getAttribute('id');

        $project = $project = Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'creds' => encrypt($credentials),
            'provider' => $provider,
            'user_id' => $userId,
        ]);

        event(new ProjectWasUpdated($project->id));

        return redirect()->route('cluster.create');
    }

    public function update(Project $project, UpdateProject $request)
    {
        $project->fill($request->validated())->save();

        event(new ProjectWasUpdated($project->id));

        return redirect()->route('settings', ['project' => $project->id]);
    }
}
