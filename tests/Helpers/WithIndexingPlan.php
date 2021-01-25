<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\IndexingPlan;
use App\Models\Subscription;
use App\Models\User;

trait WithIndexingPlan
{
    use WithRunningCluster;

    private IndexingPlan $indexingPlan;

    private function withIndexingPlan()
    {
        $this->withRunningCluster();

        $this->indexingPlan = IndexingPlan::factory()->create(['cluster_id' => $this->cluster->id]);
    }
}
