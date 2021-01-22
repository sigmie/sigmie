<?php

declare(strict_types=1);

namespace App\Http\Controllers\Indexing;

use App\Http\Requests\Indexing\StorePlan;
use App\Models\Models\IndexingPlan;
use Inertia\Inertia;

class PlanController extends \App\Http\Controllers\Controller
{
    public function store(StorePlan $request)
    {
        $plan = IndexingPlan::create($request->validated());

    }
}
