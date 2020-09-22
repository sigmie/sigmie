<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Inertia;
use Tests\TestCase;

class LeagalControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function about()
    {
        $this->expectsInertiaToRender('legal/about');

        $this->get(route('legal.about'));
    }

    /**
     * @test
     */
    public function terms()
    {
        $this->expectsInertiaToRender('legal/terms');

        $this->get(route('legal.terms'));
    }

    /**
     * @test
     */
    public function privacy()
    {
        $this->expectsInertiaToRender('legal/privacy');

        $this->get(route('legal.privacy'));
    }

    /**
     * @test
     */
    public function imprint()
    {
        $this->expectsInertiaToRender('legal/imprint');

        $this->get(route('legal.imprint'));
    }

    /**
     * @test
     */
    public function disclaimer()
    {
        $this->expectsInertiaToRender('legal/disclaimer');

        $this->get(route('legal.disclaimer'));
    }
}
