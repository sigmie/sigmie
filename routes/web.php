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

$launched = true;

Route::view('/', 'landing', ['launched' => $launched])->name('landing');

Broadcast::routes();

// Newsletter routes
Route::namespace('Newsletter')->prefix('newsletter')->name('newsletter.')->group(function () {

    Route::get('/cofirmation/{newsletterSubscription}', 'SubscriptionConfirmationController@store')->name('subscription.confirmation')->middleware(['signed', 'throttle:6,1']);

    Route::resource('/subscription', 'SubscriptionController');

    Route::view('/thank-you', 'newsletter.thankyou')->name('thankyou');

    Route::view('/confirmed', 'newsletter.confirmed')->name('confirmed');
});

// Github oauth routes
Route::namespace('Auth')->prefix('github')->name('github.')->group(function () {

    Route::get('/register', 'RegisterController@handleCallback')->name('register.redirect');

    Route::get('/redirect', 'LoginController@githubRedirect')->name('redirect');

    Route::get('/callback', 'LoginController@handleCallback')->name('callback');
});


if ($launched === true) {

    Route::get('/home', 'HomeController@index')->name('home');

    // Auth routes
    Auth::routes();

    // Legal
    Route::name('legal.')->group(function () {

        Route::view('/terms', 'legal.terms')->name('terms');

        Route::view('/privacy', 'legal.privacy')->name('privacy');

        Route::view('/cookie', 'legal.cookie')->name('cookie');
    });
}
