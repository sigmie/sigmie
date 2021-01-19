<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;

trait WithProject
{
    private User $user;

    private Project $project;

    private function withProject()
    {
        $this->user = Subscription::factory()->create()->billable;
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    }
}
