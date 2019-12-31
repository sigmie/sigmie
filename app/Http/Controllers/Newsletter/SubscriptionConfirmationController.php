<?php

namespace App\Http\Controllers\Newsletter;

use App\NewsletterSubscription;
use App\Http\Controllers\Controller;

class SubscriptionConfirmationController extends Controller
{
    /**
     * Store the subscription confirmation to
     * the newsletter subscription model
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(NewsletterSubscription $newsletterSubscription)
    {
        $newsletterSubscription->update(['confirmed' => true]);

        return redirect()->route('newsletter.confirmed');
    }
}
