<?php

declare(strict_types=1);

namespace App\Http\Controllers\Indexing;

use App\Http\Requests\Indexing\StorePlan;
use App\Http\Requests\UpdatePlan;
use App\Models\IndexingPlan;
use App\Models\PlanAttribute;

class PlanController extends \App\Http\Controllers\Controller
{
    public function __construct()
    {
        $this->authorizeResource(IndexingPlan::class, 'plan');
    }

    public function store(StorePlan $request)
    {
        $validated = $request->validated();

        $plan = IndexingPlan::create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'cluster_id' => $validated['cluster_id'],
            'frequency' => $validated['frequency']
        ]);

        if ($plan->type = 'file') {
            PlanAttribute::create([
                'name' => 'location',
                'value' => $validated['location'],
                'plan_id' => $plan->id
            ]);
        }

        PlanAttribute::create([
            'name' => 'index_alias',
            'value' => $validated['index_alias'],
            'plan_id' => $plan->id
        ]);


        return redirect(route('indexing.indexing'));
    }

    public function update(UpdatePlan $request, IndexingPlan $plan)
    {
        $plan->fill($request->validated())->save();

        return redirect(route('indexing.indexing'));
    }

    public function destroy(IndexingPlan $plan)
    {
        $plan->delete();

        return redirect(route('indexing.indexing'));
    }
}
