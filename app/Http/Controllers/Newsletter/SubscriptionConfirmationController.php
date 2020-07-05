<?php

namespace App\Http\Controllers\Newsletter;

use App\Contracts\MailingList;
use App\Events\Foo;
use App\Models\NewsletterSubscription;
use App\Http\Controllers\Controller;
use App\Services\MailgunList;

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
    public function store(NewsletterSubscription $newsletterSubscription, MailingList $mailingList)
    {
        $newsletterSubscription->confirmSubscription();

        $list = config('newsletter.list');

        dispatch(fn () => $mailingList->addToList($list, $newsletterSubscription->email, true));

        return redirect()->route('newsletter.confirmed');
    }
}
