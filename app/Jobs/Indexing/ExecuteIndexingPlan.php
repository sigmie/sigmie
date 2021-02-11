<?php

declare(strict_types=1);

namespace App\Jobs\Indexing;

use App\Contracts\Indexer;
use App\Enums\PlanState;
use App\Events\Indexing\PlanWasUpdated;
use App\Models\FileType;
use App\Models\IndexingPlan;
use App\Models\IndexingType;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ExecuteIndexingPlan implements ShouldQueue
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
        /** @var  IndexingPlan */
        $plan = IndexingPlan::find($this->planId);
        $plan->setAttribute('run_at', Carbon::now())
            ->save();

        event(new PlanWasUpdated($plan->id));

        /** @var Indexer */
        $import = $plan->type->indexer()->index();

        $plan->setAttribute('state', PlanState::NONE())
            ->save();

        event(new PlanWasUpdated($plan->id));
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $throwable)
    {
        throw $throwable;
    }
}
