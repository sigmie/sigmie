<?php

declare(strict_types=1);

namespace App\Http\Controllers\Indexing;

use App\Events\Indexing\PlanWasUpdated;
use App\Http\Requests\Indexing\StorePlan;
use App\Http\Requests\Indexing\UpdatePlan;
use App\Models\FileType;
use App\Models\IndexingActivity;
use App\Models\IndexingPlan;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PlanController extends \App\Http\Controllers\Controller
{
    public function __construct()
    {
        $this->authorizeResource(IndexingPlan::class, 'plan');
    }

    public function store(StorePlan $request)
    {
        $validated = $request->validated();

        $project = Project::find($validated['project_id']);
        $cluster = $project->clusters->first();

        $plan = new IndexingPlan([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'cluster_id' => $cluster->id,
            'cluster_type' => $cluster->getMorphClass(),
            'project_id' => $validated['project_id'],
            'random_identifier' => strtolower(Str::random(5)),
            'user_id' => Auth::id(),
        ]);

        $type = null;

        if ($validated['type']['type'] === 'file') {
            $type = FileType::create([
                'location' => $validated['type']['location'],
                'index_alias' => $validated['type']['index_alias']
            ]);
        }

        $plan->type()->associate($type)->save();

        return redirect(route('indexing.indexing'));
    }

    public function update(UpdatePlan $request, IndexingPlan $plan)
    {
        $validated = $request->validated();

        $plan->fill(
            [
                'name' => $validated['name'],
                'description' => $validated['description'],
            ]
        );

        $plan->type->delete();

        $type = null;

        if ($validated['type']['type'] === 'file') {
            $type = FileType::create([
                'location' => $validated['type']['location'],
                'index_alias' => $validated['type']['index_alias']
            ]);
        }

        $plan->type()->associate($type)->save();

        event(new PlanWasUpdated($plan->id));

        return redirect(route('indexing.indexing'));
    }

    public function deactivate(IndexingPlan $plan)
    {
        $plan->setAttribute('deactivated_at', Carbon::now())->save();

        event(new PlanWasUpdated($plan->id));

        return redirect(route('indexing.indexing'));
    }

    public function activate(IndexingPlan $plan)
    {
        $plan->setAttribute('deactivated_at', null)->save();

        event(new PlanWasUpdated($plan->id));

        return redirect(route('indexing.indexing'));
    }

    public function destroy(IndexingPlan $plan)
    {
        IndexingActivity::where('plan_id', $plan->id)->delete();

        $plan->delete();

        event(new PlanWasUpdated($plan->id));

        return redirect(route('indexing.indexing'));
    }
}
