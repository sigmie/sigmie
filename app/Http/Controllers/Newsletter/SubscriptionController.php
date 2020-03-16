<?php

namespace App\Http\Controllers\Newsletter;

use App\Contracts\MailingList;
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
    public function store(StoreNewsletterSubscription $request, MailingList $mailingList)
    {
        $subscription = NewsletterSubscription::firstOrCreate($request->validated());

        $list = config('newsletter.list');

        dispatch(fn () => $mailingList->addToList($list, $subscription->email, false, true));

        broadcast(new NewsletterSubscribed($subscription));

        return redirect()->route('newsletter.thankyou');
    }
}
