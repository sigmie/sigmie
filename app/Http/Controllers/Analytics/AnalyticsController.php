<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Models\IndexingPlan;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticsController extends \App\Http\Controllers\Controller
{
    public function __invoke(Project $project, Request $request)
    {
        $this->authorize('view', $project);

        return Inertia::render(
            'analytics/analytics',
            []
        );
    }
}
