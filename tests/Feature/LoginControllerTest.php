<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    /**
     * @test
     */
    public function login_inertia_renders_login_view()
    {
        $this->assertInertiaViewExists('auth/login/login');

        $this->get(route('login'))->assertInertia('auth/login/login');
    }
}
