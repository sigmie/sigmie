<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Middleware\Logging\LogRequest;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Inertia\Inertia;
use Tests\Helpers\ElasticsearchCleanup;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(LogRequest::class);

        $this->elasticsearchCleanup();
    }

    private function elasticsearchCleanup()
    {
        if (method_exists($this, 'deleteAllIndices')) {
            $this->deleteAllIndices();
        }
    }

    public function expectsInertiaToRender($view, ...$args)
    {
        Inertia::shouldReceive('render')->once()->with($view, ...$args);

        $this->assertFileExists(base_path("resources/js/views/{$view}.vue"));
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->elasticsearchCleanup();
    }
}
