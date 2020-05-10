<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

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
