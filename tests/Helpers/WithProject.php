<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Project;
use App\Models\User;

trait WithProject
{
    use WithSubscribedUser;

    private User $user;

    private Project $project;

    private function withProject()
    {
        $this->withSubscribedUser();

        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    }
}
