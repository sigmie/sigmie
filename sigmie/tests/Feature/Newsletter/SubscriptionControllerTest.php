<?php

declare(strict_types=1);

namespace Tests\Feature\Newsletter;

use App\Events\Newsletter\NewsletterSubscriptionWasCreated;
use App\Models\NewsletterSubscription;
use Illuminate\Support\Facades\Event;
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
    public function store_subscription()
    {
        Event::fake(NewsletterSubscriptionWasCreated::class);

        $res = $this->post(route('newsletter.subscription.store'), ['email' => 'nico@sigmie.com']);

        $res->assertSessionHasNoErrors();
        $res->assertRedirect(route('newsletter.thankyou'));

        $subscription = NewsletterSubscription::firstWhere('email', 'nico@sigmie.com');

        Event::assertDispatched(NewsletterSubscriptionWasCreated::class, function (NewsletterSubscriptionWasCreated $event) use ($subscription) {
            return $event->newsletterSubscription->id === $subscription->id;
        });

        $this->assertNotNull($subscription);
        $this->assertFalse($subscription->confirmed);
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
