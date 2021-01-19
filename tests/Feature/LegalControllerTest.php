<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LegalControllerTest extends TestCase
{
    /**
     * @test
     */
    public function about()
    {
        $this->assertInertiaViewExists('legal/about');

        $this->get(route('legal.about'))->assertInertia('legal/about');
    }

    /**
     * @test
     */
    public function terms()
    {
        $this->assertInertiaViewExists('legal/terms');

        $this->get(route('legal.terms'))->assertInertia('legal/terms');
    }

    /**
     * @test
     */
    public function privacy()
    {
        $this->assertInertiaViewExists('legal/privacy');

        $this->get(route('legal.privacy'))->assertInertia('legal/privacy');
    }

    /**
     * @test
     */
    public function imprint()
    {
        $this->assertInertiaViewExists('legal/imprint');

        $this->get(route('legal.imprint'))->assertInertia('legal/imprint');
    }

    /**
     * @test
     */
    public function disclaimer()
    {
        $this->assertInertiaViewExists('legal/disclaimer');

        $this->get(route('legal.disclaimer'))->assertInertia('legal/disclaimer');
    }
}
