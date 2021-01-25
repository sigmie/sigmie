<?php

declare(strict_types=1);

namespace App\Http\Controllers\Indexing;

use App\Models\IndexingPlan;
use App\Models\Project;
use Inertia\Inertia;

class IndexingController extends \App\Http\Controllers\Controller
{
    public function __invoke(Project $project)
    {
        $plans = IndexingPlan::forProject($project)->get(
            [
                'indexing_plans.id',
                'indexing_plans.name',
                'indexing_plans.description',
                'indexing_plans.state',
                'indexing_plans.type',
                'indexing_plans.deactivated_at',
                'indexing_plans.created_at',
                'indexing_plans.updated_at',
                'indexing_plans.run_at'
            ]
        );

        return Inertia::render('indexing/indexing', ['plans' => $plans]);
    }
}
