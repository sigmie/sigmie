<?php

declare(strict_types=1);

namespace App\Http\Controllers\Newsletter;

use App\Contracts\MailingList;
use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscription;

class SubscriptionConfirmationController extends Controller
{
    /**
     * Store the subscription confirmation to
     * the newsletter subscription model
     */
    public function store(NewsletterSubscription $newsletterSubscription, MailingList $mailingList)
    {
        $newsletterSubscription->confirmSubscription();
        $email = $newsletterSubscription->getAttribute('email');

        $list = config('newsletter.list');

        dispatch(fn () => $mailingList->addToList($list, $email, true));

        return redirect()->route('newsletter.confirmed');
    }
}
