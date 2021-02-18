<?php

declare(strict_types=1);

namespace App\Jobs\Indexing;

use App\Contracts\Indexer;
use App\Enums\ActivityTypes;
use App\Enums\PlanState;
use App\Enums\PlanTriggers;
use App\Events\Indexing\IndexingHasFailed;
use App\Events\Indexing\PlanWasUpdated;
use App\Exceptions\IndexingException;
use App\Models\FileType;
use App\Models\IndexingActivity;
use App\Models\IndexingPlan;
use App\Models\IndexingType;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class IndexPlan implements ShouldQueue
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

        try {

            $plan->type->indexer()->index();
        } catch (IndexingException $e) {

            event(new IndexingHasFailed($e));
        } finally {

            $plan->setAttribute('state', PlanState::NONE())
                ->save();

            event(new PlanWasUpdated($plan->id));
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $throwable)
    {
        throw $throwable;
    }
}
