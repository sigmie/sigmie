<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Middleware\Logging\RequestInfo;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Inertia\Inertia;
use Tests\Helpers\ElasticsearchCleanup;
use Tests\Helpers\NeedsCluster;
use Tests\Helpers\NeedsRegionRepository;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Register the available mock traits and
     * their boot methods
     */
    protected array $traits = [
        NeedsRegionRepository::class => ['regionRepository', null],
        ElasticsearchCleanup::class => ['deleteAllIndices', 'deleteAllIndices'],
        NeedsCluster::class => ['cluster', null]
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RequestInfo::class);

        $this->bootTraits();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->shutdownTraits();
    }

    public function expectsInertiaToRender($view, ...$args)
    {
        Inertia::shouldReceive('render')->once()->with($view, ...$args);

        $this->assertFileExists(base_path("resources/js/views/{$view}.vue"));
    }

    /**
     * Call the boot method for each used trait
     */
    private function bootTraits()
    {
        $usedTraits = class_uses($this);

        foreach ($this->traits as $trait => [$setupMethod, $teardownMethod]) {
            if (is_null($setupMethod)) {
                continue;
            }
            if (in_array($trait, $usedTraits)) {
                $this->$setupMethod();
            }
        }
    }

    private function shutdownTraits()
    {
        $usedTraits = class_uses($this);

        foreach ($this->traits as $trait => [$setupMethod, $teardownMethod]) {
            if (is_null($teardownMethod)) {
                continue;
            }
            if (in_array($trait, $usedTraits)) {
                $this->$teardownMethod();
            }
        }
    }
}
