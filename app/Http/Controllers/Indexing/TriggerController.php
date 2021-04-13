<?php

declare(strict_types=1);

namespace App\Http\Controllers\Indexing;

use App\Http\Controllers\Controller;
use App\Models\IndexingActivity;
use App\Models\IndexingPlan;
use Carbon\Carbon;

class TriggerController extends Controller
{
    public function __invoke(IndexingPlan $plan)
    {
        IndexingActivity::create([
            'title' => $plan->name . ' was triggered',
            'type' => IndexingActivity::TYPE_INFO,
            'trigger' => IndexingPlan::TRIGGER_MANUAL,
            'timestamp' => Carbon::now(),
            'plan_id' => $plan->id,
            'project_id' => $plan->project->id
        ]);

        $plan->run();

        return redirect(route('indexing.indexing'));
    }
}
