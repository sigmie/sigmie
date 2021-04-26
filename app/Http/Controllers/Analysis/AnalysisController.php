<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analysis;

use App\Models\Cluster;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Sigmie\Base\APIs\Calls\Cat as CatAPI;
use Illuminate\Http\Request;

class AnalysisController extends \App\Http\Controllers\Controller
{
    public function __invoke(Project $project, Request $request)
    {
        $this->authorize('view', $project);

        return Inertia::render(
            'analysis/analysis',
        );
    }
}
