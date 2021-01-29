<?php

namespace App\Http\Controllers\Indexing;

use App\Http\Controllers\Controller;
use App\Models\IndexingPlan;
use Illuminate\Support\Facades\Gate;

class TriggerController extends Controller
{
    public function __invoke(IndexingPlan $plan)
    {
        $plan->dispatch();

        return redirect(route('indexing.indexing'));
    }
}
