<?php

namespace Tests\Feature;

use App\Project;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function index_returns_json_with_auth_projects()
    {
        $users = factory(User::class, 3)
            ->create()
            ->each(function ($user) {
                factory(Project::class, 3)
                    ->make()
                    ->each(fn ($project) => $user->projects()->save($project));
            });

        $this->actingAs($users->first());
    }
}
