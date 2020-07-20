<?php

/*
|--------------------------------------------------------------------------
| Proxy Route
|--------------------------------------------------------------------------
|
| The proxy route is available on a different domain from the app.
| If the proxy domain is called the invocable proxy controller
| is dispatched.
|
*/

Route::any('/{endpoint?}/{cluster?}', 'ProxyController')->where('endpoint', '.*');
