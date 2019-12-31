<?php

namespace App\Http\Controllers\Newsletter;

use App\NewsletterSubscription;
use App\Events\NewsletterSubscribed;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsletterSubscription;

class SubscriptionController extends Controller
{
    /**
     * Store a newly created Newsletter subscription
     * if it doesn't already exist.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreNewsletterSubscription $request)
    {
        $subscription = NewsletterSubscription::firstOrNew($request->validated());

        event(new NewsletterSubscribed($subscription));

        return redirect()->route('newsletter.thankyou');
    }
}
