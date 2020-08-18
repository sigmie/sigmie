<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tool API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your tool. These routes
| are loaded by the ServiceProvider of your tool. They are protected
| by your tool's "Authorize" middleware by default. Now, go build!
|
*/

Route::get('/all', 'Sigmie\NovaFeatureFlags\Http\FeatureFlagController@all');

Route::patch('/on/{feature}', 'Sigmie\NovaFeatureFlags\Http\FeatureFlagController@on');

Route::patch('/off/{feature}', 'Sigmie\NovaFeatureFlags\Http\FeatureFlagController@off');
