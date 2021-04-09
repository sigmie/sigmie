<?php

declare(strict_types=1);

namespace App\Http\Controllers\Project;

use App\Models\Cluster;
use App\Models\Project;
use Inertia\Inertia;

class SettingsController extends \App\Http\Controllers\Controller
{
    /**
     * Show project settings page
     */
    public function index(Project $project)
    {
        $cluster = $project->clusters->first();

        return Inertia::render('project/settings/settings', [
            'cluster' => $cluster?->settingsData(),
            'project' => $project->only(['id', 'name', 'description']),
        ]);
    }
}
