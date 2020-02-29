<?php

namespace App\Http\Controllers\Newsletter;

use App\Events\Foo;
use App\NewsletterSubscription;
use App\Http\Controllers\Controller;
use App\Services\Mailgun;

class SubscriptionConfirmationController extends Controller
{
    /**
     * Store the subscription confirmation to
     * the newsletter subscription model
     *
     * @param NewsletterSubscription $newsletterSubscription
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(NewsletterSubscription $newsletterSubscription)
    {
        $newsletterSubscription->confirmSubscription();

        return redirect()->route('newsletter.confirmed');
    }
}
