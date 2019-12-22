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

Route::group([], function () {

    Route::get('/', function () {
        return view('welcome');
    });

    Route::get('/home', 'HomeController@index')->name('home');
});

Auth::routes();


Route::view('/terms-of-service', 'static.terms-of-service')->name('terms-of-service');

Route::view('/privacy-policy', 'static.privacy-policy')->name('privacy-policy');

Route::view('/cookies-policy', 'static.cookie-policy')->name('cookie-policy');
