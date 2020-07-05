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

use App\Events\ClusterWasCreated;
use App\Http\Middleware\RedirectIfHasCluster;
use App\Listeners\PollState;

$launched = true;

Route::get('/', 'LandingController')->name('landing')->middleware('guest');

// Newsletter routes
Route::namespace('Newsletter')->prefix('newsletter')->name('newsletter.')->group(function () {

    Route::get('/confirmation/{newsletterSubscription}', 'SubscriptionConfirmationController@store')->name('subscription.confirmation')->middleware(['signed', 'throttle:6,1']);

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

    Auth::routes();

    // Legal
    Route::name('legal.')->group(function () {

        Route::view('/terms', 'legal.terms')->name('terms');

        Route::view('/privacy', 'legal.privacy')->name('privacy');

        Route::view('/cookie', 'legal.cookie')->name('cookie');
    });

    Route::group(['middleware' => ['auth', 'user', 'projects']], function () {

        Route::resource('project', 'ProjectController');

        Route::group(['middleware' => ['project']], function () {

            Route::get('/dashboard/{project?}', 'DashboardController')->name('dashboard');

            Route::get('/access-tokens', 'DashboardController')->name('access-token.index');

            Route::get('/settings', 'SettingsController@index')->name('settings');

            Route::get('/playground', 'DashboardController')->name('playground');

            Route::get('/monitoring', 'DashboardController')->name('monitoring');

            Route::get('/cluster/create', 'ClusterController@create')->name('cluster.create')->middleware(RedirectIfHasCluster::class);
            Route::get('/cluster/edit/{cluster}', 'ClusterController@edit')->name('cluster.edit');
            Route::post('/cluster', 'ClusterController@store')->name('cluster.store');
            Route::put('/cluster/{cluster}', 'ClusterController@update')->name('cluster.update');
            Route::delete('/cluster/{project}', 'ClusterController@destroy')->name('cluster.destroy');
        });
    });
}

Route::bind('cluster', function ($id) {
    return App\Models\Cluster::withTrashed()->where('id', $id)->first();
});

Broadcast::routes();
