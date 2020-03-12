<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    /**
     * @test
     */
    public function gest_redirects_to_login()
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }
}
