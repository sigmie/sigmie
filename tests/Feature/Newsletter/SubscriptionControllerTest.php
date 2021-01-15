<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    /**
     * @test
     */
    public function confirmed_renders_newsletter_confirmed(): void
    {
        $this->assertInertiaViewExists('newsletter/confirmed');

        $this->get(route('newsletter.confirmed'))->assertInertia('newsletter/confirmed');
    }

    /**
     * @test
     */
    public function thankyou_renders_newsletter_thankyou(): void
    {
        $this->assertInertiaViewExists('newsletter/thankyou');

        $this->get(route('newsletter.thankyou'))->assertInertia('newsletter/thankyou');
    }
}
