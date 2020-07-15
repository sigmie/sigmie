<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\ApiTokenController;
use Inertia\Inertia;
use Tests\TestCase;

class ApiTokenControllerTest extends TestCase
{
    /**
     * @var ApiTokenController
     */
    private $controller;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = new ApiTokenController;
    }

    /**
     * @test
     */
    public function index_renders_inertia_view(): void
    {
        Inertia::shouldReceive('render')->once()->with('api-token/index');

        $this->controller->index();
    }
}
