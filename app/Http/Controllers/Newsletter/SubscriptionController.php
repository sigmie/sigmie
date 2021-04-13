<?php

declare(strict_types=1);

namespace App\Http\Controllers\Newsletter;

use App\Events\Newsletter\NewsletterSubscriptionWasCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Newsletter\StoreSubscription;
use App\Models\NewsletterSubscription;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    /**
     * Store a newly created Newsletter subscription
     * if it doesn't already exist.
     */
    public function store(StoreSubscription $request)
    {
        $subscription = NewsletterSubscription::firstOrCreate($request->validated());

        event(new NewsletterSubscriptionWasCreated($subscription));

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
