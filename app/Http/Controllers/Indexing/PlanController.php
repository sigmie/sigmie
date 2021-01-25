<?php

declare(strict_types=1);

namespace App\Http\Controllers\Indexing;

use App\Http\Requests\Indexing\StorePlan;
use App\Http\Requests\UpdatePlan;
use App\Models\IndexingPlan;
use Inertia\Inertia;
use Throwable;

class PlanController extends \App\Http\Controllers\Controller
{
    public function __construct()
    {
        $this->authorizeResource(IndexingPlan::class, 'plan');
    }

    public function store(StorePlan $request)
    {
        IndexingPlan::create($request->validated());

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
