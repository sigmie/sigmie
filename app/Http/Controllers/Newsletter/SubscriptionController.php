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
     * @param StoreNewsletterSubscription $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreNewsletterSubscription $request)
    {
        $subscription = NewsletterSubscription::firstOrCreate($request->validated());

        event(new NewsletterSubscribed($subscription));

        return redirect()->route('newsletter.thankyou');
    }
}
