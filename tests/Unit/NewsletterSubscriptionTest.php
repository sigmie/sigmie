<?php

namespace Tests\Unit;

use App\Models\NewsletterSubscription;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    /**
     * @test
     */
    public function confirmed_attribute_casts_to_boolean(): void
    {
        $newsletterSubscription = new NewsletterSubscription(['confirmed' => 1]);

        $this->assertTrue($newsletterSubscription->confirmed);
    }

    /**
     * @test
     */
    public function confirmed_default_value_is_false(): void
    {
        $newsletterSubscription = new NewsletterSubscription();

        $this->assertFalse($newsletterSubscription->confirmed);
    }
}
