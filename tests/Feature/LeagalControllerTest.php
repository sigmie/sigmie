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
        Inertia::shouldReceive('render')->once()->with('legal/about');

        $this->get(route('legal.about'));
    }

    /**
     * @test
     */
    public function terms()
    {
        Inertia::shouldReceive('render')->once()->with('legal/terms');

        $this->get(route('legal.terms'));
    }

    /**
     * @test
     */
    public function privacy()
    {
        Inertia::shouldReceive('render')->once()->with('legal/privacy');

        $this->get(route('legal.privacy'));
    }

    /**
     * @test
     */
    public function imprint()
    {
        Inertia::shouldReceive('render')->once()->with('legal/imprint');

        $this->get(route('legal.imprint'));
    }

    /**
     * @test
     */
    public function disclaimer()
    {
        Inertia::shouldReceive('render')->once()->with('legal/disclaimer');

        $this->get(route('legal.disclaimer'));
    }
}
