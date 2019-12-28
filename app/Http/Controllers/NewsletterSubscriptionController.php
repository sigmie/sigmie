<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewsletterSubscription;
use App\NewsletterSubscription;
use Illuminate\Http\Request;

class NewsletterSubscriptionController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreNewsletterSubscription $request)
    {
        $values = $request->validated();

        $subscription = NewsletterSubscription::create($values);

        $subscription->sendEmailConfirmationNotification();

        return redirect()->route('landing');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\NewsletterSubscription  $newletterSubscription
     * @return \Illuminate\Http\Response
     */
    public function show(NewsletterSubscription $newletterSubscription)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\NewsletterSubscription  $newletterSubscription
     * @return \Illuminate\Http\Response
     */
    public function edit(NewsletterSubscription $newletterSubscription)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\NewsletterSubscription  $newletterSubscription
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, NewsletterSubscription $newletterSubscription)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\NewsletterSubscription  $newletterSubscription
     * @return \Illuminate\Http\Response
     */
    public function destroy(NewsletterSubscription $newletterSubscription)
    {
        //
    }
}
