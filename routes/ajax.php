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

Route::resource('/notification', 'NotificationController', ['except' => ['edit', 'create', 'destroy']])->middleware('auth');

Route::get('/cluster/validate/name/{name}', 'ClusterValidationController@name')->name('cluster.validate.name');

Route::post('/project/validate/provider', 'ProjectValidationController@provider')->name('project.validate.provider');

Route::put('/tokens/{cluster}/regenerate/{token}', 'ClusterTokenController@regenerate')->name('token.regenerate');

Route::put('/tokens/{cluster}/toogle/{token}', 'ClusterTokenController@toogle')->name('token.toogle');
