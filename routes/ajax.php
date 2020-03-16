<?php

/*
|--------------------------------------------------------------------------
| Ajax Routes
|--------------------------------------------------------------------------
|
| Here is where you can register Ajax routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "ajax" middleware group. Returning json responses
|
*/

use App\Events\Foo;
use App\Notifications\ProjectWasCreated;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Auth;

Route::group(['middleware' => 'auth'], fn () => Route::resource('/project', 'ProjectController'));

Route::resource('/notification', 'NotificationController', ['except' => ['edit', 'create', 'destroy']]);

Route::get('/notify/me', function () {

    Auth::user()->notify(new ProjectWasCreated('Sigma search'));

    dd('notifed');

    return;
});
