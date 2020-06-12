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

$launched = false;

Route::get('/', 'LandingController')->name('landing')->middleware('guest');

// Newsletter routes
Route::namespace('Newsletter')->prefix('newsletter')->name('newsletter.')->group(function () {

    Route::get('/cofirmation/{newsletterSubscription}', 'SubscriptionConfirmationController@store')->name('subscription.confirmation')->middleware(['signed', 'throttle:6,1']);

    Route::resource('/subscription', 'SubscriptionController');

    Route::get('/thank-you', 'SubscriptionController@thankyou')->name('thankyou');

    Route::get('/confirmed', 'SubscriptionController@confirmed')->name('confirmed');
});

if ($launched === true) {

    // Github auth routes
    Route::namespace('Auth')->prefix('github')->name('github.')->group(function () {

        Route::get('/redirect', 'GithubController@redirect')->name('redirect');

        Route::get('/login', 'GithubController@login')->name('login');

        Route::get('/register', 'GithubController@register')->name('register');
    });

    Route::group(['middleware' => ['auth']], function () {

        Route::get('/dashboard/{project?}', 'DashboardController')->name('dashboard');

        Route::get('/access-tokens', 'DashboardController')->name('access-token');

        Route::get('/playground', 'DashboardController')->name('playground');

        Route::get('/monitoring', 'DashboardController')->name('monitoring');

        Route::resource('cluster', 'ClusterController');

        Route::resource('project', 'ProjectController');
    });

    Auth::routes();

    // Legal
    Route::name('legal.')->group(function () {

        Route::view('/terms', 'legal.terms')->name('terms');

        Route::view('/privacy', 'legal.privacy')->name('privacy');

        Route::view('/cookie', 'legal.cookie')->name('cookie');
    });
}
