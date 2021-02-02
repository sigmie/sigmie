<?php

declare(strict_types=1);

namespace App\Jobs\Indexing;

use App\Enums\PlanState;
use App\Models\IndexingPlan;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteIndexingPlan implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $planId)
    {
        $this->queue = 'long-running-queue';
    }

    public function handle(): void
    {
        $plan = IndexingPlan::find($this->planId);
        $plan->setAttribute('run_at', Carbon::now())
            ->save();

        ray('handled')->green();

        $plan->setAttribute('state', PlanState::NONE())
            ->save();
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $throwable)
    {
        throw $throwable;
    }
}