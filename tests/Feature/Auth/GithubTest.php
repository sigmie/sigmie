<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GithubTest extends TestCase
{
    /**
     * Github register redirect
     *
     * @test
     */
    public function testRedirectRoute()
    {
        $response = $this->get('/github/redirect');

        // Redirect
        $response->assertStatus(302);
    }
}
