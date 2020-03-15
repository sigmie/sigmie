<?php

namespace App\Http\Controllers\Newsletter;

use App\NewsletterSubscription;
use App\Events\NewsletterSubscribed;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsletterSubscription;
use App\Services\MailgunList;
use Exception;

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

        /** @var  MailgunList */
        $mailgun = resolve(MailgunList::class);
        $list = config('services.mailgun.newsletter_list');

        dispatch(fn () => $mailgun->addToList($list, $subscription->email, false, true));

        broadcast(new NewsletterSubscribed($subscription));

        return redirect()->route('newsletter.thankyou');
    }
}
