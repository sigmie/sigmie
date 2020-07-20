<?php

/*
|--------------------------------------------------------------------------
| Proxy Routes
|--------------------------------------------------------------------------
*/

use Illuminate\Http\Request;

Route::get('/user', function (Request $request) {
    return $request->user();
});
