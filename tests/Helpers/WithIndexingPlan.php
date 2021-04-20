<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\IndexingPlan;

trait WithIndexingPlan
{
    use WithRunningExternalCluster;

    private IndexingPlan $indexingPlan;

    private function withIndexingPlan()
    {
        $this->withRunningExternalCluster();

        $this->indexingPlan = IndexingPlan::factory()->create([
            'cluster_id' => $this->cluster->id,
            'cluster_type' => $this->cluster->getMorphClass(),
            'user_id' => $this->user->id,
            'project_id' => $this->cluster->project->id
        ]);
    }
}
