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
        /** @var  Cluster */
        $cluster = $project->clusters()->first();

        return Inertia::render('project/settings/settings', [
            'cluster' => $cluster?->only(['id', 'state', 'allowedIps']),
            'project' => $project->only(['id', 'name', 'description']),
        ]);
    }
}
