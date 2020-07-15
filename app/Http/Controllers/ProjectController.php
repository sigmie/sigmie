<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProject;
use App\Repositories\ProjectRepository;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProjectController extends Controller
{
    private ProjectRepository $projects;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projects = $projectRepository;
    }

    /**
     * Project create page
     */
    public function create()
    {
        return Inertia::render('project/create');
    }

    public function store(StoreProject $request)
    {
        $credentials = json_decode($request->get('provider')['creds'], true);
        $provider = $request->get('provider')['id'];
        $userId = Auth::user()->getAttribute('id');

        $this->projects->create([
            'name' => $request->get('name'),
            'description' => $request->get('description'),
            'creds' => encrypt($credentials),
            'provider' => $provider,
            'user_id' => $userId
        ]);

        return redirect()->route('cluster.create');
    }
}
