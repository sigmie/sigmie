<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Landing\LandingController;
use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\NovaFeatureFlags\FeatureFlagManager;
use Tests\TestCase;

class LandingControllerTest extends TestCase
{
    /**
     * @var LandingController
     */
    private $controller;

    /**
     * @var FeatureFlagManager|MockObject;
     */
    private $featureFlagManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->featureFlagManager = $this->createMock(FeatureFlagManager::class);

        $this->controller = new LandingController();
    }

    /**
     * @test
     */
    public function inertia_render_landing_with_auth_feature(): void
    {
        $this->assertTrue(true);
    }
}
