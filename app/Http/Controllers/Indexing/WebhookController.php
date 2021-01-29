<?php

namespace App\Http\Controllers\Indexing;

use App\Http\Controllers\Controller;
use App\Models\Indexing\Plan;
use App\Models\IndexingPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WebhookController extends Controller
{
    public function __invoke(IndexingPlan $plan)
    {
        $user = $plan->cluster->findUser();

        if (Gate::forUser($user)->allows('trigger-webhook')) {

            $plan->dispatch();

            return;
        }

        return abort(401);
    }
}
