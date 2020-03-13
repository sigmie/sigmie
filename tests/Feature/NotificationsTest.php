<?php

namespace Tests\Feature;

use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class NotificationsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function index_returns_json_with_auth_projects()
    {
        $users = factory(User::class)->create();

        $this->actingAs($users->first());

        $this->get('/ajax/project')->assertJsonCount(3);
    }
}
