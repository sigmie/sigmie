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

        /** @var  Mailgun */
        $mailgun = resolve(Mailgun::class);
        $list = config('services.mailgun.newsletter_list');

        dispatch(fn () => $mailgun->confirmSubscription($list, $newsletterSubscription->email));

        return redirect()->route('newsletter.confirmed');
    }
}
