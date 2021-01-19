<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Middleware\Logging\RequestInfo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Sigmie\Testing\Laravel\Traits as SigmieTraits;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use DatabaseTransactions;
    use SigmieTraits;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RequestInfo::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Boot the testing helper traits.
     *
     * @return array
     */
    protected function setUpTraits()
    {
        $uses = parent::setUpTraits();

        $this->setUpSigmieTraits($uses);

        return $uses;
    }

    protected function assertInertiaViewExists($view)
    {
        $this->assertFileExists(base_path("resources/js/views/{$view}.vue"));
    }
}
