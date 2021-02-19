<?php

declare(strict_types=1);

namespace App\Listeners\Indexing;

use App\Enums\ActivityTypes;
use App\Events\Indexing\IndexingHasFailed;
use App\Models\IndexingActivity;
use Carbon\Carbon;

class CreateErrorActivity
{
    public function handle(IndexingHasFailed $event)
    {
        IndexingActivity::create([
            'title' => $event->indexingException->getMessage(),
            'type' => (string) ActivityTypes::ERROR(),
            'timestamp' => Carbon::now(),
            'plan_id' => $event->indexingException->plan->id,
            'project_id' => $event->indexingException->plan->project->id
        ]);
    }
}
