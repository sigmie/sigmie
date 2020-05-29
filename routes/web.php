<?php

use Inertia\Inertia;

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


Route::get('/', 'LandingController')->name('landing')->middleware('guest');

// Newsletter routes
Route::namespace('Newsletter')->prefix('newsletter')->name('newsletter.')->group(function () {

    Route::get('/cofirmation/{newsletterSubscription}', 'SubscriptionConfirmationController@store')->name('subscription.confirmation')->middleware(['signed', 'throttle:6,1']);

    Route::resource('/subscription', 'SubscriptionController');

    Route::view('/thank-you', 'newsletter.thankyou')->name('thankyou');

    Route::view('/confirmed', 'newsletter.confirmed')->name('confirmed');
});

// Github auth routes
Route::namespace('Auth')->prefix('github')->name('github.')->group(function () {

    Route::get('/redirect', 'GithubController@redirect')->name('redirect');

    Route::get('/login', 'GithubController@login')->name('login');

    Route::get('/register', 'GithubController@register')->name('register');
});


if ($launched === true) {

    Route::group(['middleware' => ['auth']], function () {

        Route::get('/dashboard', 'DashboardController')->name('dashboard');

        Route::get('/access-tokens', 'DashboardController')->name('access-token');

        Route::get('/playground', 'DashboardController')->name('playground');

        Route::get('/monitoring', 'DashboardController')->name('monitoring');

        Route::resource('cluster', 'ClusterController');
    });

    // Auth routes
    Route::get('login', [
        'as' => 'login',
        'uses' => 'Auth\LoginController@showLoginForm'
    ]);

    Route::post('login', [
        'as' => '',
        'uses' => 'Auth\LoginController@login'
    ]);

    Route::post('logout', [
        'as' => 'logout',
        'uses' => 'Auth\LoginController@logout'
    ]);

    // Password Reset Routes...
    Route::post('password/email', [
        'as' => 'password.email',
        'uses' => 'Auth\ForgotPasswordController@sendResetLinkEmail'
    ]);

    Route::get('password/reset', [
        'as' => 'password.request',
        'uses' => 'Auth\ForgotPasswordController@showLinkRequestForm'
    ]);

    Route::post('password/reset', [
        'as' => 'password.update',
        'uses' => 'Auth\ResetPasswordController@reset'
    ]);

    Route::get('password/reset/{token}', [
        'as' => 'password.reset',
        'uses' => 'Auth\ResetPasswordController@showResetForm'
    ]);

    // Registration Routes...
    Route::get('register', [
        'as' => 'register',
        'uses' => 'Auth\RegisterController@showRegistrationForm'
    ]);
    Route::post('register', [
        'as' => '',
        'uses' => 'Auth\RegisterController@register'
    ]);

    // Legal
    Route::name('legal.')->group(function () {

        Route::view('/terms', 'legal.terms')->name('terms');

        Route::view('/privacy', 'legal.privacy')->name('privacy');

        Route::view('/cookie', 'legal.cookie')->name('cookie');
    });
}
