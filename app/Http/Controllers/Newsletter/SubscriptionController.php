<?php

namespace App\Http\Controllers\Newsletter;

use App\NewsletterSubscription;
use App\Events\NewsletterSubscribed;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsletterSubscription;
use App\Services\Mailgun;

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

        /** @var  Mailgun */
        $mailgun = resolve(Mailgun::class);
        $list = config('services.mailgun.newsletter_list');

        dispatch_now(fn() => $mailgun->addToList($list, $subscription->email));

        broadcast(new NewsletterSubscribed($subscription));

        return redirect()->route('newsletter.thankyou');
    }
}
