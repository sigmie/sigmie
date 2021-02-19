<?php declare(strict_types=1);

namespace App\Http\Controllers\Indexing;

use App\Enums\ActivityTypes;
use App\Enums\PlanTriggers;
use App\Http\Controllers\Controller;
use App\Models\IndexingActivity;
use App\Models\IndexingPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

class PingController extends Controller
{
    public function __invoke(IndexingPlan $plan)
    {
        $user = $plan->cluster->findUser();

        if (Gate::forUser($user)->allows('trigger-plan') && $plan->isActive()) {
            IndexingActivity::create([
                'title' => $plan->name . ' was triggered',
                'type' => (string) ActivityTypes::INFO(),
                'trigger' => (string) PlanTriggers::PING(),
                'timestamp' => Carbon::now(),
                'plan_id' => $plan->id,
                'project_id' => $plan->project->id
            ]);

            $plan->run();

            return;
        }

        abort(401);
    }
}
