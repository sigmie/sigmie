<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Laravel\Nova\Console\DashboardCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DashboardControllerTest extends TestCase
{
    /**
     * @var DashboardController
     */
    private $controller;

    /**
     * @var Request|MockObject
     */
    private $requestMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->requestMock = $this->createMock(Request::class);

        $this->controller = new DashboardController;
    }
}
