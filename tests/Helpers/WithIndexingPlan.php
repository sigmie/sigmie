<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\IndexingPlan;
use App\Models\User;

trait WithIndexingPlan
{
    use WithRunningCluster;

    private IndexingPlan $indexingPlan;

    private function withIndexingPlan(bool $withWebhook = false, User $user = null)
    {
        $this->withRunningCluster($user);

        $this->indexingPlan = IndexingPlan::factory()->create(['cluster_id' => $this->cluster->id]);

        if ($withWebhook) {
            $this->indexingPlan->createWebhook();
        }
    }
}
