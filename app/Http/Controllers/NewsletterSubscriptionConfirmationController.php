<?php

namespace App\Http\Controllers;

use App\NewsletterSubscription;
use Illuminate\Http\Request;

class NewsletterSubscriptionConfirmationController extends Controller
{
    public function store(NewsletterSubscription $newsletterSubscription)
    {
        $newsletterSubscription->update(['confirmed' => true]);

        return redirect()->route('newsletter-subscription.confirmed');
    }
}
