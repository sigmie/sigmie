<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Cluster;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;

trait WithSubscribedUser
{
    private User $user;

    private function withSubscribedUser()
    {
        $this->user = Subscription::factory()->create()->billable;
    }
}
