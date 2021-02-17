<?php

namespace App\Http\Controllers\Indexing;

use App\Enums\ActivityTypes;
use App\Enums\PlanTriggers;
use App\Http\Controllers\Controller;
use App\Models\IndexingActivity;
use App\Models\IndexingPlan;
use Carbon\Carbon;

class TriggerController extends Controller
{
    public function __invoke(IndexingPlan $plan)
    {
        IndexingActivity::create([
            'type' => (string) ActivityTypes::DISPATCH(),
            'trigger' => (string) PlanTriggers::MANUAL(),
            'timestamp' => Carbon::now(),
            'plan_id' => $plan->id,
            'project_id' => $plan->project->id
        ]);

        $plan->run();

        return redirect(route('indexing.indexing'));
    }
}
