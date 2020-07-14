<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\LandingController;
use Inertia\Inertia;
use Tests\TestCase;

class LandingControllerTest extends TestCase
{
    /**
     * @var LandingController
     */
    private $controller;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = new LandingController;
    }

    /**
     * @test
     */
    public function inertia_render_landing(): void
    {
        Inertia::shouldReceive('render')->once()->with('landing');

        ($this->controller)();
    }
}
