<?php

namespace App\Http\Controllers\Newsletter;

use App\Contracts\MailingList;
use App\Models\NewsletterSubscription;
use App\Events\NewsletterSubscriptionWasCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsletterSubscription;
use Inertia\Inertia;

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
    public function store(StoreNewsletterSubscription $request, MailingList $mailingList)
    {
        $subscription = NewsletterSubscription::firstOrCreate($request->validated());

        broadcast(new NewsletterSubscriptionWasCreated($subscription));

        return redirect()->route('newsletter.thankyou');
    }

    public function thankyou()
    {
        return Inertia::render('newsletter/thankyou');
    }

    public function confirmed()
    {
        return Inertia::render('newsletter/confirmed');
    }
}
