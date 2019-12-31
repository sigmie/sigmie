<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/home', 'HomeController@index')->name('home');

// Auth routes
Auth::routes();

// Resource routes below
Route::namespace('Newsletter')->prefix('newsletter')->name('newsletter.')->group(function () {

    Route::get('/cofirmation/{newsletterSubscription}', 'SubscriptionConfirmationController@store')->name('subscription.confirmation')->middleware(['signed', 'throttle:6,1']);

    Route::resource('/subscription', 'SubscriptionController');

    Route::view('/thank-you', 'newsletter.thankyou')->name('thankyou');

    Route::view('/confirmed', 'newsletter.confirmed')->name('confirmed');
});

// Static routes below
Route::view('/', 'static.landing')->name('landing');


Route::view('/terms', 'static.terms')->name('terms');

Route::view('/privacy', 'static.privacy')->name('privacy');

Route::view('/cookie', 'static.cookie')->name('cookie');
