<?php

declare(strict_types=1);

namespace App\Http\Controllers\Project;

use App\Models\Project;
use Inertia\Inertia;

class SettingsController extends \App\Http\Controllers\Controller
{
    /**
     * Show project settings page
     */
    public function index(Project $project)
    {
        $cluster = $project->getAttribute('clusters')->first();

        dd($cluster->allowedIps);
        return Inertia::render('project/settings/settings', [
            'cluster' => $cluster?->only(['id', 'state', 'allowed_ips']),
            'project' => $project->only(['id', 'name', 'description']),
        ]);
    }
}
