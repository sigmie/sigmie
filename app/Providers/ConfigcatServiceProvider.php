<?php

namespace App\Providers;

use ConfigCat\ConfigCatClient;
use ConfigCat\Cache\LaravelCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class ConfigcatServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('ConfigCat\ConfigCatClient', function () {
            return new ConfigCatClient(config('services.configcat.key'), [
                'cache' => new LaravelCache(Cache::store()),
                'cache-refresh-interval' => 5
            ]);
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
