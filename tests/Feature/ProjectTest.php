<?php

namespace Tests\Feature;

use App\Project;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

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

        $this->get('/ajax/project')->assertJsonCount(3);
    }

    /**
     * @test
     */
    public function index_returns_empty_json_to_guests()
    {
        $this->getJson('/ajax/project')->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
